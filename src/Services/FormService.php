<?php

namespace Modules\Glitter\FormBuilder\Services;

use App\Helpers\PermissionHelper;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Modules\Glitter\FormBuilder\Enums\FormAccessMode;
use Modules\Glitter\FormBuilder\Enums\FormStatus;
use Modules\Glitter\FormBuilder\Enums\RetentionMode;
use Modules\Glitter\FormBuilder\Exceptions\Schema\FormSchemaValidationException;
use Modules\Glitter\FormBuilder\Fields\ConsentDefinitionDefaults;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Models\FormVersion;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormRepositoryInterface;
use Modules\Glitter\FormBuilder\Repositories\Contracts\FormVersionRepositoryInterface;
use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use Modules\Glitter\FormBuilder\Schema\PublishSchemaValidator;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class FormService
{
    public function __construct(
        private FormRepositoryInterface $forms,
        private FormVersionRepositoryInterface $versions,
        private FormSchemaParser $schemaParser,
        private PublishSchemaValidator $publishSchemaValidator,
    ) {}

    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->forms->paginate($filters, $perPage);
    }

    public function find(string $uuid): Form
    {
        $form = $this->forms->findOrFailByUuid($uuid);
        $this->assertScope($form, 'read');

        return $form;
    }

    public function create(array $data): Form
    {
        $acknowledged = (bool) ($data['retention_acknowledged'] ?? false);
        unset($data['retention_acknowledged']);
        if (($data['retention_mode'] ?? RetentionMode::Manual->value) !== RetentionMode::Days->value) {
            $data['retention_days'] = null;
        }

        return $this->forms->create(array_merge($data, [
            'status' => FormStatus::Draft,
            'access_mode' => $data['access_mode'] ?? FormAccessMode::AuthenticatedOnly,
            'retention_mode' => $data['retention_mode'] ?? RetentionMode::Manual,
            'retention_acknowledged_at' => $acknowledged ? now() : null,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]));
    }

    public function update(Form $form, array $data): Form
    {
        $this->assertScope($form, 'update');
        if ($form->status === FormStatus::Archived) {
            throw new \DomainException(__('glitter-form_builder::messages.form.archived_read_only'));
        }
        if ($form->current_published_version_id !== null && isset($data['slug']) && $data['slug'] !== $form->slug) {
            throw new \DomainException(__('glitter-form_builder::messages.form.slug_immutable'));
        }

        if (array_key_exists('retention_acknowledged', $data)) {
            $data['retention_acknowledged_at'] = $data['retention_acknowledged'] ? now() : null;
            unset($data['retention_acknowledged']);
        }
        $retentionMode = $data['retention_mode'] ?? $form->retention_mode->value;
        if ($retentionMode !== RetentionMode::Days->value) {
            $data['retention_days'] = null;
        }
        $data['updated_by'] = Auth::id();

        return $this->forms->update($form, $data);
    }

    public function delete(Form $form): bool
    {
        $this->assertScope($form, 'delete');
        if ($form->current_published_version_id !== null || $form->status !== FormStatus::Draft) {
            $this->forms->update($form, [
                'status' => FormStatus::Archived,
                'updated_by' => Auth::id(),
            ]);

            return true;
        }

        return $this->forms->delete($form);
    }

    public function publish(Form $form): FormVersion
    {
        $this->assertScope($form, 'publish');

        return DB::transaction(function () use ($form): FormVersion {
            $locked = $this->forms->lockById((int) $form->id);
            if ($locked->status === FormStatus::Archived) {
                throw new \DomainException(__('glitter-form_builder::messages.form.archived_read_only'));
            }
            if ($locked->retention_acknowledged_at === null) {
                throw new \DomainException(__('glitter-form_builder::messages.form.retention_not_acknowledged'));
            }

            $validation = $this->validatePublishSchema($locked);
            if ($validation->hasErrors()) {
                throw new FormSchemaValidationException($validation);
            }

            $revision = $this->versions->nextRevision($locked);
            $publishedAt = now();
            $draftSchema = ConsentDefinitionDefaults::normalizeLegacySchema(is_array($locked->draft_schema) ? $locked->draft_schema : []);
            $version = $this->versions->create([
                'form_id' => $locked->id,
                'revision' => $revision,
                'schema_version' => (string) ($draftSchema['schemaVersion'] ?? '1.0'),
                'snapshot' => $this->snapshot($locked, $draftSchema, $revision, $publishedAt->toISOString()),
                'published_by' => Auth::id(),
                'published_at' => $publishedAt,
            ]);

            $update = [
                'status' => FormStatus::Published,
                'current_published_version_id' => $version->id,
                'published_at' => $publishedAt,
                'updated_by' => Auth::id(),
            ];
            if ($draftSchema !== $locked->draft_schema) {
                $update['draft_schema'] = $draftSchema;
            }
            $this->forms->update($locked, $update);

            return $version;
        });
    }

    public function validatePublishSchema(Form $form): SchemaValidationResult
    {
        $defaultLocale = (string) config('app.locale', 'ko');
        $supportedLocales = array_values(array_unique(array_merge(
            [$defaultLocale],
            array_keys(is_array($form->title) ? $form->title : []),
            array_keys(is_array($form->description) ? $form->description : []),
        )));

        return $this->publishSchemaValidator->validate(
            $this->schemaParser->parse(ConsentDefinitionDefaults::normalizeLegacySchema(is_array($form->draft_schema) ? $form->draft_schema : [])),
            $defaultLocale,
            $supportedLocales,
        );
    }

    /** @param array<string, mixed> $draftSchema */
    private function snapshot(Form $form, array $draftSchema, int $revision, string $publishedAt): array
    {
        return [
            'schemaVersion' => (string) ($draftSchema['schemaVersion'] ?? '1.0'),
            'form' => [
                'publicId' => $form->uuid,
                'slug' => $form->slug,
                'revision' => $revision,
                'accessMode' => $form->access_mode->value,
                'title' => $form->title,
                'description' => $form->description,
                'retention' => [
                    'mode' => $form->retention_mode->value,
                    'days' => $form->retention_days,
                    'automaticEnforcement' => false,
                ],
            ],
            'fields' => $draftSchema['fields'] ?? [],
            'providerSnapshot' => $draftSchema['providerSnapshot'] ?? [],
            'publishedAt' => $publishedAt,
        ];
    }

    protected function assertScope(Form $form, string $action): void
    {
        if (! PermissionHelper::checkScopeAccess($form, 'glitter-form_builder.forms.'.$action)) {
            throw new AccessDeniedHttpException(__('auth.scope_denied'));
        }
    }
}
