<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Schema;

use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use PHPUnit\Framework\TestCase;

final class FormSchemaParserTest extends TestCase
{
    public function test_minimal_schema_preserves_field_order_without_registry_dependency(): void
    {
        $schema = (new FormSchemaParser)->parse([
            'schemaVersion' => '1.0',
            'fields' => [['key' => 'second'], ['key' => 'first']],
        ]);

        self::assertSame('1.0', $schema->schemaVersion);
        self::assertSame(['second', 'first'], array_column($schema->fields, 'key'));
        self::assertSame([], $schema->parsingIssues);
    }

    public function test_malformed_fields_becomes_safe_parsing_issue(): void
    {
        $schema = (new FormSchemaParser)->parse(['schemaVersion' => '1.0', 'fields' => 'bad']);

        self::assertSame([], $schema->fields);
        self::assertSame('schema.fields_not_list', $schema->parsingIssues[0]->code);
    }

    public function test_non_data_value_and_invalid_utf8_fail_closed(): void
    {
        foreach ([new \stdClass, "\xB1\x31"] as $unsafe) {
            $schema = (new FormSchemaParser)->parse(['schemaVersion' => '1.0', 'fields' => [], 'form' => ['unsafe' => $unsafe]]);
            self::assertContains('schema.not_json_safe', array_map(static fn ($issue): string => $issue->code, $schema->parsingIssues));
        }
    }
}
