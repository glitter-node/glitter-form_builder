<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Phase5;

use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use Modules\Glitter\FormBuilder\Schema\FormSchemaValidator;
use Modules\Glitter\FormBuilder\Schema\PublishSchemaValidator;
use Modules\Glitter\FormBuilder\Services\SchemaEditingService;
use PHPUnit\Framework\TestCase;

final class SchemaEditingServiceTest extends TestCase
{
    public function test_invalid_draft_is_result_not_transport_exception(): void
    {
        $service = $this->service();
        $result = $service->validate(['schemaVersion' => '1.0', 'fields' => [['key' => 'bad-key']]], 'en', ['en'], 'draft');
        self::assertTrue($result->hasErrors());
    }

    public function test_only_declared_modes_are_accepted(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service()->validate(['schemaVersion' => '1.0', 'fields' => []], 'en', ['en'], 'other');
    }

    public function test_endpoint_controller_does_not_import_write_dependencies(): void
    {
        $source = file_get_contents(__DIR__.'/../../../src/Http/Controllers/Admin/SchemaValidationController.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('FormRepository', $source);
        self::assertStringNotContainsString('FormService', $source);
        self::assertStringNotContainsString('DB::', $source);
        self::assertStringContainsString("return \$this->success", $source);
    }

    public function test_built_in_renderer_is_available_for_publish_preview(): void
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $service = new SchemaEditingService(new FormSchemaParser, new FormSchemaValidator, new PublishSchemaValidator(new FormSchemaValidator, $registry));
        $field = [
            'key' => 'name', 'type' => 'text', 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0',
            'label' => ['en' => 'Name'], 'description' => [], 'required' => false, 'default' => null,
            'options' => [], 'validation' => [], 'config' => ['trim' => true], 'sensitive' => false, 'indexable' => false, 'exportable' => true,
        ];
        $result = $service->validate(['schemaVersion' => '1.0', 'fields' => [$field]], 'en', ['en'], 'publish-preview');
        self::assertFalse($result->hasErrors());
        self::assertNotContains('field.renderer_unavailable', $result->warningCodes());
    }

    private function service(): SchemaEditingService
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        return new SchemaEditingService(new FormSchemaParser, new FormSchemaValidator, new PublishSchemaValidator(new FormSchemaValidator, $registry));
    }
}
