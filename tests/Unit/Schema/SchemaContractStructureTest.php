<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Schema;

use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\FormSchema;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationIssue;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;

final class SchemaContractStructureTest extends TestCase
{
    public function test_schema_namespace_has_no_db_http_repository_or_external_module_dependency(): void
    {
        $root = dirname(__DIR__, 3).'/src/Schema';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') continue;
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            self::assertDoesNotMatchRegularExpression('/use (?:Illuminate\\\\(?:Database|Http)|Modules\\\\(?:Sirsoft|Glitter\\\\(?:Crm|Reservation|Board|Ecommerce))|Modules\\\\Glitter\\\\Formbuilder\\\\Repositories)/', $source);
        }
    }

    public function test_parser_does_not_depend_on_registry_and_results_are_readonly(): void
    {
        $parserSource = file_get_contents((new ReflectionClass(FormSchemaParser::class))->getFileName());
        self::assertStringNotContainsString('FieldTypeRegistry', $parserSource);
        foreach ([FormSchema::class, SchemaValidationIssue::class, SchemaValidationResult::class] as $class) self::assertTrue((new ReflectionClass($class))->isReadOnly());
    }

    public function test_http_and_repository_layers_do_not_resolve_providers_or_validate_schema(): void
    {
        $module = dirname(__DIR__, 3).'/src';
        $controller = file_get_contents($module.'/Http/Controllers/Admin/FormController.php');
        self::assertStringNotContainsString('FieldTypeRegistry', $controller);
        foreach (glob($module.'/Repositories/{Contracts/,}*.php', GLOB_BRACE) ?: [] as $file) {
            $source = file_get_contents($file);
            self::assertStringNotContainsString('SchemaValidator', $source);
            self::assertStringNotContainsString('PublishSchemaValidator', $source);
        }
    }
}
