<?php

namespace Modules\Glitter\FormBuilder\Tests\Unit;

use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\CheckboxFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\DatetimeFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\HiddenFieldProvider;
use Modules\Glitter\FormBuilder\Fields\BuiltIn\NumberFieldProvider;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;
use PHPUnit\Framework\TestCase;

final class BuiltInFieldBehaviorTest extends TestCase
{
    public function test_checkbox_is_single_boolean_not_group(): void
    {
        $this->expectException(FieldNormalizationException::class);
        (new CheckboxFieldProvider)->normalize(['a'], self::field('checkbox'), self::context());
    }

    public function test_number_rejects_float_and_scientific_notation(): void
    {
        foreach ([1.2, '1e3'] as $value) {
            try {
                (new NumberFieldProvider)->normalize($value, self::field('number'), self::context());
                self::fail('Expected number failure.');
            } catch (FieldNormalizationException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function test_datetime_accepts_offset_and_normalizes_to_utc(): void
    {
        $result = (new DatetimeFieldProvider)->normalize('2026-07-31T09:30:00+09:00', self::field('datetime'), self::context());
        self::assertSame('2026-07-31T00:30:00Z', $result->normalizedValue);
    }

    public function test_hidden_ignores_client_value(): void
    {
        $field = new FieldDefinition('field_key', 'hidden', ['en' => 'Hidden'], defaultValue: 'server', sensitive: true, configuration: ['source' => 'schema_default']);
        $result = (new HiddenFieldProvider)->normalize('client', $field, self::context());
        self::assertSame('server', $result->normalizedValue);
    }

    private static function field(string $type): FieldDefinition { return new FieldDefinition('field_key', $type, ['en' => 'Field']); }
    private static function context(): FieldValidationContext { return new FieldValidationContext('00000000-0000-0000-0000-000000000001', 1, 'field_key', 'en', 'admin_only', true, 'Asia/Seoul'); }
}
