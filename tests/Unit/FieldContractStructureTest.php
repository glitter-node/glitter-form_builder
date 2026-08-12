<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Modules\Glitter\FormBuilder\Contracts\Fields\FieldProvider;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class FieldContractStructureTest extends TestCase
{
    public function test_public_contract_has_no_framework_or_external_domain_boundary(): void
    {
        $root = dirname(__DIR__, 2).'/src';
        $targets = [$root.'/Contracts/Fields', $root.'/Fields'];
        foreach ($targets as $target) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target)) as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') continue;
                $contents = file_get_contents($file->getPathname());
                self::assertIsString($contents);
                self::assertDoesNotMatchRegularExpression('/use Illuminate\\\\(Database\\\\Eloquent\\\\Model|Http\\\\Request)/', $contents);
                self::assertDoesNotMatchRegularExpression('/Modules\\\\(?:Sirsoft|Glitter\\\\(?:Crm|Reservation))/', $contents);
                self::assertStringNotContainsString('RecursiveDirectoryIterator', basename($file->getPathname()) === 'FieldContractStructureTest.php' ? '' : $contents);
            }
        }
    }

    public function test_contract_and_registry_surface_are_intentional(): void
    {
        self::assertSame('1.0', FieldProvider::CONTRACT_VERSION);
        self::assertFalse(method_exists(FieldTypeRegistry::class, 'unregister'));
        self::assertFalse(method_exists(FieldTypeRegistry::class, 'discover'));
    }

    public function test_public_value_objects_are_readonly(): void
    {
        foreach (glob(dirname(__DIR__, 2).'/src/Fields/ValueObjects/*.php') ?: [] as $file) {
            $class = 'Modules\\Glitter\\FormBuilder\\Fields\\ValueObjects\\'.basename($file, '.php');
            self::assertTrue((new \ReflectionClass($class))->isReadOnly(), $class);
        }
    }
}
