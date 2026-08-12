<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Phase6;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Glitter\FormBuilder\Enums\FormAccessMode;
use Modules\Glitter\FormBuilder\Exceptions\Rendering\PublicFormProjectionException;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Models\Form;
use Modules\Glitter\FormBuilder\Models\FormVersion;
use Modules\Glitter\FormBuilder\Rendering\FormLayoutProjector;
use Modules\Glitter\FormBuilder\Repositories\Contracts\PublicFormRepositoryInterface;
use Modules\Glitter\FormBuilder\Services\PublicFormService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PublicFormServiceTest extends TestCase
{
    #[DataProvider('accessCases')]
    public function test_access_modes_are_enforced(FormAccessMode $mode, bool $authenticated, bool $admin, bool $allowed): void
    {
        $service = $this->service($this->form($mode));

        if (! $allowed) {
            $this->expectException(ModelNotFoundException::class);
        }
        $result = $service->schema('contact-us', $authenticated, $admin);
        if ($allowed) {
            self::assertSame('contact-us', $result->form['slug']);
        }
    }

    public static function accessCases(): array
    {
        return [
            'guest public' => [FormAccessMode::GuestAndAuthenticated, false, false, true],
            'guest authenticated-only' => [FormAccessMode::AuthenticatedOnly, false, false, false],
            'member authenticated-only' => [FormAccessMode::AuthenticatedOnly, true, false, true],
            'member admin-only' => [FormAccessMode::AdminOnly, true, false, false],
            'admin admin-only' => [FormAccessMode::AdminOnly, true, true, true],
        ];
    }

    public function test_missing_or_unpublished_form_is_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);
        $this->service(null)->schema('missing', false, false);
    }

    public function test_corrupt_published_snapshot_is_an_internal_projection_failure(): void
    {
        $form = $this->form(FormAccessMode::GuestAndAuthenticated);
        $form->uuid = '01989f2e-2f2c-7d53-8f13-112233445566';
        $form->currentPublishedVersion->id = 42;
        $form->currentPublishedVersion->snapshot = ['schemaVersion' => 'broken'];

        try {
            $this->service($form)->schema('contact-us', false, false);
            self::fail('Expected projection failure.');
        } catch (PublicFormProjectionException $exception) {
            self::assertSame('01989f2e-2f2c-7d53-8f13-112233445566', $exception->formUuid);
            self::assertSame('contact-us', $exception->slug);
            self::assertSame(42, $exception->versionId);
            self::assertSame('public_schema.invalid_snapshot', $exception->category);
        }
    }

    private function service(?Form $form): PublicFormService
    {
        $repository = new class($form) implements PublicFormRepositoryInterface
        {
            public function __construct(private readonly ?Form $form) {}

            public function findPublishedBySlug(string $slug): ?Form
            {
                return $this->form;
            }
        };
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);

        return new PublicFormService($repository, new FormLayoutProjector($registry));
    }

    private function form(FormAccessMode $mode): Form
    {
        $version = new FormVersion;
        $version->forceFill(['snapshot' => [
            'schemaVersion' => '1.0',
            'form' => [
                'publicId' => '01989f2e-2f2c-7d53-8f13-112233445566', 'slug' => 'contact-us', 'revision' => 1,
                'accessMode' => $mode->value, 'title' => ['en' => 'Contact'], 'description' => [],
            ],
            'fields' => [],
            'publishedAt' => '2026-07-31T00:00:00Z',
        ]]);
        $form = new Form;
        $form->forceFill(['access_mode' => $mode->value]);
        $form->setRelation('currentPublishedVersion', $version);

        return $form;
    }
}
