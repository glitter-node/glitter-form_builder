<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\InvalidFieldDefinitionException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class NumberFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'number'; }
    protected function storageKind(): string { return 'decimal'; }
    protected function indexType(): string { return 'number'; }
    protected function validationKeys(): array { return ['min', 'max', 'integer', 'decimalPlaces']; }
    protected function specificRules(FieldDefinition $field): array
    {
        $rules = [['rule' => 'decimal_string'], ['rule' => 'scientific_notation_forbidden']];
        foreach (['min', 'max', 'integer', 'decimalPlaces'] as $key) if (array_key_exists($key, $field->validation)) $rules[] = ['rule' => $key, 'value' => $field->validation[$key]];
        return $rules;
    }
    protected function validateSpecificDefinition(FieldDefinition $field): void
    {
        foreach (['min', 'max'] as $key) if (isset($field->validation[$key])) self::canonical((string) $field->validation[$key]);
        if (isset($field->validation['decimalPlaces']) && (! is_int($field->validation['decimalPlaces']) || $field->validation['decimalPlaces'] < 0)) throw new InvalidFieldDefinitionException('field_definition.invalid_decimal_places');
    }
    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        if ($empty = $this->emptyOrFail($value, $field)) return $empty;
        if (is_float($value) || (! is_string($value) && ! is_int($value))) throw new FieldNormalizationException('field_normalization.invalid_number');
        $canonical = self::canonical((string) $value);
        if (($field->validation['integer'] ?? false) && str_contains($canonical, '.')) throw new FieldNormalizationException('field_normalization.integer_required');
        $places = str_contains($canonical, '.') ? strlen(substr(strrchr($canonical, '.'), 1)) : 0;
        if (isset($field->validation['decimalPlaces']) && $places > $field->validation['decimalPlaces']) throw new FieldNormalizationException('field_normalization.too_many_decimal_places');
        if (isset($field->validation['min']) && self::compare($canonical, self::canonical((string) $field->validation['min'])) < 0) throw new FieldNormalizationException('field_normalization.below_minimum');
        if (isset($field->validation['max']) && self::compare($canonical, self::canonical((string) $field->validation['max'])) > 0) throw new FieldNormalizationException('field_normalization.above_maximum');
        return $this->result($canonical, $field);
    }
    private static function canonical(string $value): string
    {
        if (! preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?$/', $value)) throw new FieldNormalizationException('field_normalization.invalid_number');
        $negative = str_starts_with($value, '-');
        $unsigned = $negative ? substr($value, 1) : $value;
        [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $fraction = rtrim($fraction, '0');
        $canonical = $integer.($fraction !== '' ? '.'.$fraction : '');
        return $negative && $canonical !== '0' ? '-'.$canonical : $canonical;
    }
    private static function compare(string $left, string $right): int
    {
        $leftNegative = str_starts_with($left, '-'); $rightNegative = str_starts_with($right, '-');
        if ($leftNegative !== $rightNegative) return $leftNegative ? -1 : 1;
        $l = ltrim($left, '-'); $r = ltrim($right, '-');
        [$li, $lf] = array_pad(explode('.', $l, 2), 2, ''); [$ri, $rf] = array_pad(explode('.', $r, 2), 2, '');
        $cmp = strlen($li) <=> strlen($ri); if ($cmp === 0) $cmp = $li <=> $ri;
        if ($cmp === 0) { $width = max(strlen($lf), strlen($rf)); $cmp = str_pad($lf, $width, '0') <=> str_pad($rf, $width, '0'); }
        return $leftNegative ? -$cmp : $cmp;
    }
}
