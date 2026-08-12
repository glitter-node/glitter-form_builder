<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit\Schema;

use Modules\Glitter\FormBuilder\Fields\BuiltIn\BuiltInFieldProviders;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\TextFieldProvider;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Schema\FormSchemaParser;
use Modules\Glitter\FormBuilder\Schema\FormSchemaValidator;
use Modules\Glitter\FormBuilder\Schema\PublishSchemaValidator;
use PHPUnit\Framework\TestCase;

final class PublishSchemaValidatorTest extends TestCase
{
    public function test_valid_builtin_schema_has_available_renderer_and_is_valid(): void
    {
        $result = $this->validator()->validate((new FormSchemaParser)->parse(self::schema([self::field('email', 'email', sensitive: true)])), 'en', ['en']);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->warningCodes());
    }

    public function test_unresolved_and_invalid_fields_accumulate_without_raw_values(): void
    {
        $schema = self::schema([
            self::field('custom', 'vendor-fields:custom', provider: 'vendor-fields'),
            self::field('bad_email', 'email', default: 'SECRET-invalid', sensitive: true),
        ]);
        $result = $this->validator()->validate((new FormSchemaParser)->parse($schema), 'en', ['en']);

        self::assertSame(['field.provider_unresolved', 'field.definition_invalid'], $result->errorCodes());
        self::assertStringNotContainsString('SECRET-invalid', json_encode($result->toArray(), JSON_THROW_ON_ERROR));
        self::assertSame('fields.0.type', $result->errors()[0]->path);
        self::assertSame('fields.1', $result->errors()[1]->path);
    }

    public function test_incompatible_contract_and_duplicate_options_fail_closed(): void
    {
        $field = self::field('choice', 'select');
        $field['contractVersion'] = '2.0';
        $field['options'] = [
            ['value' => 'same', 'label' => ['en' => 'One']],
            ['value' => 'same', 'label' => ['en' => 'Two']],
        ];
        $result = $this->validator()->validate((new FormSchemaParser)->parse(self::schema([$field])), 'en', ['en']);
        self::assertContains('field.provider_incompatible', $result->errorCodes());

        $field['contractVersion'] = '1.0';
        $result = $this->validator()->validate((new FormSchemaParser)->parse(self::schema([$field])), 'en', ['en']);
        self::assertContains('field.definition_invalid', $result->errorCodes());
    }

    public function test_external_provider_without_phase_six_allowlisted_renderer_cannot_publish(): void
    {
        $registry = new FieldTypeRegistry;
        $registry->register(new class extends TextFieldProvider
        {
            public function type(): string
            {
                return 'acme-fields:text';
            }

            public function providerId(): string
            {
                return 'acme-fields';
            }
        });
        $registry->seal();
        $validator = new PublishSchemaValidator(new FormSchemaValidator, $registry);
        $result = $validator->validate(
            (new FormSchemaParser)->parse(self::schema([self::field('external', 'acme-fields:text', provider: 'acme-fields')])),
            'en',
            ['en'],
        );

        self::assertContains('field.renderer_unavailable', $result->errorCodes());
        self::assertFalse($result->isValid());
    }

    public function test_consent_contract_rejects_the_browser_fixture_and_accepts_canonical_defaults(): void
    {
        $legacy = self::consentField(null, false, true);
        $legacyResult = $this->validator()->validate((new FormSchemaParser)->parse(self::schema([$legacy])), 'en', ['en']);
        self::assertContains('field.definition_invalid', $legacyResult->errorCodes());

        foreach ([false, null] as $default) {
            $valid = self::consentField('consent', $default, true);
            $result = $this->validator()->validate((new FormSchemaParser)->parse(self::schema([$valid])), 'en', ['en']);
            self::assertTrue($result->isValid(), json_encode($result->toArray(), JSON_THROW_ON_ERROR));
        }

        foreach ([self::consentField('consent', true, true), self::consentField('consent', false, false)] as $invalid) {
            $result = $this->validator()->validate((new FormSchemaParser)->parse(self::schema([$invalid])), 'en', ['en']);
            self::assertContains('field.definition_invalid', $result->errorCodes());
        }
    }

    public function test_text_email_and_canonical_consent_publish_together(): void
    {
        $result = $this->validator()->validate((new FormSchemaParser)->parse(self::schema([
            self::field('text_1', 'text'),
            self::field('email_1', 'email', sensitive: true),
            self::consentField('consent', false, true),
        ])), 'en', ['en']);

        self::assertTrue($result->isValid(), json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    private function validator(): PublishSchemaValidator
    {
        $registry = new FieldTypeRegistry;
        BuiltInFieldProviders::register($registry);
        $registry->seal();

        return new PublishSchemaValidator(new FormSchemaValidator, $registry);
    }

    private static function schema(array $fields): array
    {
        return ['schemaVersion' => '1.0', 'fields' => $fields];
    }

    private static function field(string $key, string $type, string $provider = 'glitter-form_builder', mixed $default = null, bool $sensitive = false): array
    {
        return ['key' => $key, 'type' => $type, 'provider' => $provider, 'contractVersion' => '1.0', 'label' => ['en' => ucfirst($key)], 'description' => [], 'required' => false, 'default' => $default, 'options' => [], 'validation' => [], 'config' => [], 'sensitive' => $sensitive, 'indexable' => false, 'exportable' => true];
    }

    private static function consentField(mixed $statementId, mixed $default, bool $required): array
    {
        $field = self::field('consent_1', 'consent', default: $default);
        $field['required'] = $required;
        $field['config'] = ['statementId' => $statementId];

        return $field;
    }
}
