<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Modules\Glitter\FormBuilder\Contracts\Fields\FieldProvider;
use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\CheckboxFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\ConsentFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\DateFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\DatetimeFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\EmailFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\HiddenFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\NumberFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\RadioFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\SelectFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\TextFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\TextareaFieldProvider;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FieldProviderConformanceTest extends TestCase
{
    /** @return iterable<string, array{FieldProvider, FieldDefinition, mixed, mixed}> */
    public static function cases(): iterable
    {
        $choice = [['value' => 'sales', 'label' => ['en' => 'Sales']]];
        yield 'text' => [new TextFieldProvider, self::field('text', validation: ['maxLength' => 10]), ' hello ', 'hello'];
        yield 'textarea' => [new TextareaFieldProvider, self::field('textarea'), "a\r\nb", "a\nb"];
        yield 'email' => [new EmailFieldProvider, self::field('email', sensitive: true), ' User@Example.com ', 'User@Example.com'];
        yield 'number' => [new NumberFieldProvider, self::field('number'), '-12.3400', '-12.34'];
        yield 'select' => [new SelectFieldProvider, self::field('select', options: $choice), 'sales', 'sales'];
        yield 'radio' => [new RadioFieldProvider, self::field('radio', options: $choice), 'sales', 'sales'];
        yield 'checkbox' => [new CheckboxFieldProvider, self::field('checkbox'), 'on', true];
        yield 'date' => [new DateFieldProvider, self::field('date'), '2026-07-31', '2026-07-31'];
        yield 'datetime' => [new DatetimeFieldProvider, self::field('datetime'), '2026-07-31T09:30', '2026-07-31T00:30:00Z'];
        yield 'hidden' => [new HiddenFieldProvider, self::field('hidden', default: 'server', sensitive: true, configuration: ['source' => 'schema_default']), 'tampered', 'server'];
        yield 'consent' => [new ConsentFieldProvider, self::field('consent', required: true, configuration: ['statementId' => 'privacy-v1']), 'yes', true];
    }

    #[DataProvider('cases')]
    public function test_provider_contract(FieldProvider $provider, FieldDefinition $field, mixed $raw, mixed $expected): void
    {
        $provider->validateDefinition($field);
        $descriptor = $provider->validationDescriptor($field, self::context($field));
        $result = $provider->normalize($raw, $field, self::context($field));

        self::assertSame($expected, $result->normalizedValue);
        self::assertSame($expected, $provider->serialize($result));
        self::assertNotSame('', $descriptor->messageKey);
        json_encode($descriptor->toArray(), JSON_THROW_ON_ERROR);
        json_encode($provider->rendererDescriptor()->toArray(), JSON_THROW_ON_ERROR);
        if ($field->sensitive) self::assertSame('••••••', $provider->display($result, 'en'));
    }

    public function test_sensitive_failure_does_not_echo_raw_value(): void
    {
        $raw = 'secret-invalid-email';
        try {
            (new EmailFieldProvider)->normalize($raw, self::field('email', sensitive: true), self::context(self::field('email')));
            self::fail('Expected normalization failure.');
        } catch (FieldNormalizationException $exception) {
            self::assertStringNotContainsString($raw, $exception->getMessage());
        }
    }

    private static function field(string $type, array $options = [], array $validation = [], bool $required = false, mixed $default = null, bool $sensitive = false, array $configuration = []): FieldDefinition
    {
        return new FieldDefinition('field_key', $type, ['en' => 'Field'], required: $required, defaultValue: $default, options: $options, validation: $validation, sensitive: $sensitive, indexable: ! $sensitive && $type !== 'hidden', configuration: $configuration);
    }

    private static function context(FieldDefinition $field): FieldValidationContext
    {
        return new FieldValidationContext('00000000-0000-0000-0000-000000000001', 1, $field->key, 'en', 'guest_and_authenticated', false, 'Asia/Seoul');
    }
}
