<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Schema;

use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use Modules\Glitter\FormBuilder\Schema\FormSchemaValidator;
use Modules\Glitter\FormBuilder\Schema\SchemaLimits;
use PHPUnit\Framework\TestCase;

final class FormSchemaValidatorTest extends TestCase
{
    public function test_frontend_canonical_text_email_and_consent_definitions_are_valid(): void
    {
        $field = static fn (string $type, bool $required = false, mixed $default = null, array $config = []): array => [
            'key' => $type.'_1', 'type' => $type, 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0',
            'label' => ['ko' => $type], 'description' => [], 'required' => $required, 'default' => $default,
            'options' => [], 'validation' => [], 'config' => $config, 'sensitive' => $type === 'email',
            'indexable' => false, 'exportable' => true,
        ];
        $schema = (new FormSchemaParser)->parse(['schemaVersion' => '1.0', 'fields' => [
            $field('text', config: ['trim' => true]),
            $field('email'),
            $field('consent', true, false, ['statementId' => 'privacy']),
        ]]);
        $result = (new FormSchemaValidator)->validate($schema, 'ko', ['ko', 'en'], false);

        self::assertTrue($result->isValid());
        self::assertNotContains('field.key_invalid', $result->errorCodes());
        self::assertNotContains('field.locale_unsupported', $result->errorCodes());
    }

    public function test_structural_issues_are_aggregated_in_stable_order(): void
    {
        $schema = (new FormSchemaParser)->parse([
            'unknown' => true,
            'fields' => [
                ['key' => 'id', 'type' => 'text', 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0', 'label' => ['en' => 'A'], 'required' => false, 'sensitive' => false, 'indexable' => false, 'exportable' => true],
                ['key' => 'id', 'type' => 'text', 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0', 'label' => [], 'required' => false, 'sensitive' => false, 'indexable' => false, 'exportable' => true],
            ],
        ]);
        $result = (new FormSchemaValidator)->validate($schema, 'en', ['en', 'ko'], false);

        self::assertFalse($result->isValid());
        self::assertContains('schema.version_missing', $result->errorCodes());
        self::assertContains('schema.unknown_key', $result->errorCodes());
        self::assertContains('field.key_reserved', $result->errorCodes());
        self::assertContains('field.key_duplicate', $result->errorCodes());
        self::assertContains('field.label_missing', $result->errorCodes());
        self::assertSame('schemaVersion', $result->errors()[0]->path);
    }

    public function test_limits_are_named_and_enforced(): void
    {
        $fields = array_fill(0, SchemaLimits::MAX_FIELDS + 1, []);
        $schema = (new FormSchemaParser)->parse(['schemaVersion' => '1.0', 'fields' => $fields]);
        $result = (new FormSchemaValidator)->validate($schema, 'en', ['en'], false);

        self::assertContains('schema.too_many_fields', $result->errorCodes());
    }

    public function test_missing_default_locale_is_warning_for_draft_and_error_for_publish(): void
    {
        $field = ['key' => 'name', 'type' => 'text', 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0', 'label' => ['ko' => '이름'], 'required' => false, 'sensitive' => false, 'indexable' => false, 'exportable' => true];
        $schema = (new FormSchemaParser)->parse(['schemaVersion' => '1.0', 'fields' => [$field]]);
        $validator = new FormSchemaValidator;

        self::assertContains('field.default_locale_missing', $validator->validate($schema, 'en', ['ko', 'en'], false)->warningCodes());
        self::assertContains('field.default_locale_missing', $validator->validate($schema, 'en', ['ko', 'en'], true)->errorCodes());
    }

    public function test_size_depth_option_and_unknown_field_limits_fail_closed(): void
    {
        $nested = 'leaf';
        for ($i = 0; $i < SchemaLimits::MAX_NESTING_DEPTH + 2; $i++) $nested = ['node' => $nested];
        $field = ['key' => 'choice', 'type' => 'select', 'provider' => 'glitter-form_builder', 'contractVersion' => '1.0', 'label' => ['en' => 'Choice'], 'required' => false, 'sensitive' => false, 'indexable' => false, 'exportable' => true, 'options' => array_fill(0, SchemaLimits::MAX_OPTIONS_PER_FIELD + 1, []), 'config' => ['nested' => $nested], 'unexpected' => true];
        $schema = (new FormSchemaParser)->parse(['schemaVersion' => '1.0', 'fields' => [$field], 'form' => ['padding' => str_repeat('x', SchemaLimits::MAX_ENCODED_BYTES)]]);
        $codes = (new FormSchemaValidator)->validate($schema, 'en', ['en'], false)->errorCodes();

        self::assertContains('schema.too_large', $codes);
        self::assertContains('schema.too_deep', $codes);
        self::assertContains('field.too_many_options', $codes);
        self::assertContains('field.unknown_key', $codes);
    }
}
