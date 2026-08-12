<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Phase6;

use PHPUnit\Framework\TestCase;

final class PublicRenderingStructureTest extends TestCase
{
    public function test_public_read_and_phase_seven_submission_routes_are_separate(): void
    {
        $routes = file_get_contents(__DIR__.'/../../../src/routes/api.php');
        self::assertIsString($routes);
        self::assertStringContainsString("Route::get('/{slug}/schema'", $routes);
        self::assertStringContainsString("Route::post('/{slug}/submissions'", $routes);
        self::assertStringContainsString('glitter-form_builder-submissions', $routes);
        self::assertStringNotContainsString('SubmissionHandlerRegistry', $routes);
    }

    public function test_user_layout_uses_only_allowlisted_components_and_i18n_sentences(): void
    {
        $path = __DIR__.'/../../../resources/layouts/user/user_form_builder_form_show.json';
        $layout = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('_user_base', $layout['extends']);

        $encoded = json_encode($layout, JSON_THROW_ON_ERROR);
        foreach (['Div', 'H1', 'Span', 'Form', 'Label', 'Textarea', 'Select', 'Checkbox', 'Input', 'Button'] as $component) {
            self::assertStringContainsString('"name":"'.$component.'"', $encoded);
        }
        self::assertStringNotContainsString('dangerouslySetInnerHTML', $encoded);
        self::assertStringNotContainsString('apiCall', $encoded);
        self::assertStringContainsString('$t:glitter-form_builder.public.form.submit', $encoded);
        self::assertStringContainsString('glitter-form_builder.submitForm', $encoded);
    }

    public function test_public_resource_does_not_expose_orm_or_request_in_value_object(): void
    {
        $source = file_get_contents(__DIR__.'/../../../src/Rendering/ValueObjects/PublicFormSchema.php');
        self::assertIsString($source);
        self::assertStringContainsString('final readonly class PublicFormSchema', $source);
        self::assertStringNotContainsString('Eloquent', $source);
        self::assertStringNotContainsString('Request', $source);
        self::assertStringNotContainsString('Container', $source);
    }

    public function test_public_controller_separates_not_found_from_logged_projection_failure(): void
    {
        $source = file_get_contents(__DIR__.'/../../../src/Http/Controllers/User/PublicFormSchemaController.php');
        self::assertIsString($source);
        self::assertStringContainsString('catch (ModelNotFoundException)', $source);
        self::assertStringContainsString('catch (PublicFormProjectionException $exception)', $source);
        self::assertStringContainsString("'error_category' => \$exception->category", $source);
        self::assertStringContainsString("'version_uuid' => \$exception->versionUuid", $source);
        self::assertStringContainsString("public_schema.internal_error', 500", $source);
    }
}
