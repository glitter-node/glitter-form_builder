<?php

namespace Modules\Glitter\FormBuilder\Services;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Glitter\FormBuilder\Enums\FormAccessMode;
use Modules\Glitter\FormBuilder\Exceptions\Rendering\PublicFormProjectionException;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Rendering\FormLayoutProjector;
use Modules\Glitter\FormBuilder\Rendering\ValueObjects\PublicFormSchema;
use Modules\Glitter\FormBuilder\Repositories\Contracts\PublicFormRepositoryInterface;
use Throwable;

final class PublicFormService
{
    public function __construct(
        private readonly PublicFormRepositoryInterface $forms,
        private readonly FormLayoutProjector $projector,
    ) {}

    public function schema(string $slug, bool $authenticated, bool $admin): PublicFormSchema
    {
        $form = $this->forms->findPublishedBySlug($slug);
        if ($form === null || $form->currentPublishedVersion === null || ! $this->canView($form->access_mode, $authenticated, $admin)) {
            throw (new ModelNotFoundException)->setModel(Form::class);
        }

        $snapshot = $form->currentPublishedVersion->snapshot;
        try {
            if (! is_array($snapshot)) {
                throw new \DomainException('public_schema.invalid_snapshot');
            }

            return $this->projector->project($snapshot);
        } catch (Throwable $exception) {
            $category = str_starts_with($exception->getMessage(), 'public_schema.')
                ? $exception->getMessage()
                : 'public_schema.unexpected_projection_failure';

            throw new PublicFormProjectionException(
                (string) $form->uuid,
                $slug,
                (int) $form->currentPublishedVersion->getKey(),
                is_string($form->currentPublishedVersion->getAttribute('uuid'))
                    ? $form->currentPublishedVersion->getAttribute('uuid')
                    : null,
                $category,
                $exception,
            );
        }
    }

    private function canView(FormAccessMode $mode, bool $authenticated, bool $admin): bool
    {
        return match ($mode) {
            FormAccessMode::GuestAndAuthenticated => true,
            FormAccessMode::AuthenticatedOnly => $authenticated,
            FormAccessMode::AdminOnly => $authenticated && $admin,
        };
    }
}
