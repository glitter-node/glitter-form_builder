<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

class TextFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'text'; }
    protected function storageKind(): string { return 'string'; }
    protected function configurationKeys(): array { return ['trim']; }
    protected function validationKeys(): array { return ['minLength', 'maxLength', 'in']; }

    protected function specificRules(FieldDefinition $field): array
    {
        $rules = [['rule' => 'string'], ['rule' => 'single_line']];
        foreach (['minLength' => 'min_length', 'maxLength' => 'max_length', 'in' => 'in'] as $key => $rule) {
            if (array_key_exists($key, $field->validation)) $rules[] = ['rule' => $rule, 'value' => $field->validation[$key]];
        }
        return $rules;
    }

    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        if ($empty = $this->emptyOrFail($value, $field)) return $empty;
        if (! is_string($value) || str_contains($value, "\n") || str_contains($value, "\r")) throw new FieldNormalizationException('field_normalization.invalid_text');
        $value = ($field->configuration['trim'] ?? true) ? trim($value) : $value;
        $this->assertLength($value, $field);
        if (isset($field->validation['in']) && ! in_array($value, $field->validation['in'], true)) throw new FieldNormalizationException('field_normalization.not_allowed');
        return $this->result($value, $field);
    }

    protected function assertLength(string $value, FieldDefinition $field): void
    {
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if (isset($field->validation['minLength']) && $length < $field->validation['minLength']) throw new FieldNormalizationException('field_normalization.too_short');
        if (isset($field->validation['maxLength']) && $length > $field->validation['maxLength']) throw new FieldNormalizationException('field_normalization.too_long');
    }
}
