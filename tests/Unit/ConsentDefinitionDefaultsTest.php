<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Modules\Glitter\FormBuilder\Fields\ConsentDefinitionDefaults;
use PHPUnit\Framework\TestCase;

final class ConsentDefinitionDefaultsTest extends TestCase
{
    public function test_legacy_null_and_empty_statement_ids_are_normalized_at_the_resource_boundary(): void
    {
        $schema = ['schemaVersion' => '1.0', 'fields' => [
            ['type' => 'consent', 'provider' => 'glitter-form_builder', 'config' => ['statementId' => null]],
            ['type' => 'consent', 'provider' => 'glitter-form_builder', 'config' => ['statementId' => '']],
            ['type' => 'consent', 'provider' => 'vendor-fields', 'config' => ['statementId' => null]],
        ]];

        $normalized = ConsentDefinitionDefaults::normalizeLegacySchema($schema);

        self::assertSame('consent', $normalized['fields'][0]['config']['statementId']);
        self::assertSame('consent', $normalized['fields'][1]['config']['statementId']);
        self::assertNull($normalized['fields'][2]['config']['statementId']);
    }

    public function test_valid_or_missing_statement_ids_and_unrelated_arrays_are_unchanged(): void
    {
        $schema = ['schemaVersion' => '1.0', 'fields' => [
            ['type' => 'consent', 'provider' => 'glitter-form_builder', 'config' => ['statementId' => 'privacy-v1'], 'options' => []],
            ['type' => 'consent', 'provider' => 'glitter-form_builder', 'config' => []],
            ['type' => 'text', 'provider' => 'glitter-form_builder', 'config' => []],
        ]];

        $normalized = ConsentDefinitionDefaults::normalizeLegacySchema($schema);

        self::assertSame($schema, $normalized);
    }
}
