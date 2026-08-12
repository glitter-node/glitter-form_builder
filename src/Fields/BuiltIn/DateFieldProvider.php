<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use DateTimeImmutable;
use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class DateFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'date'; }
    protected function storageKind(): string { return 'date'; }
    protected function validationKeys(): array { return ['min', 'max']; }
    protected function specificRules(FieldDefinition $field): array { return [['rule' => 'date_format', 'value' => 'Y-m-d']]; }
    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        if ($empty = $this->emptyOrFail($value, $field)) return $empty;
        if (! is_string($value) || ! self::valid($value)) throw new FieldNormalizationException('field_normalization.invalid_date');
        if (isset($field->validation['min']) && $value < $field->validation['min']) throw new FieldNormalizationException('field_normalization.below_minimum');
        if (isset($field->validation['max']) && $value > $field->validation['max']) throw new FieldNormalizationException('field_normalization.above_maximum');
        return $this->result($value, $field);
    }
    private static function valid(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) && $date->format('Y-m-d') === $value;
    }
}
