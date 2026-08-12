<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\InvalidFieldDefinitionException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

abstract class AbstractChoiceFieldProvider extends AbstractFieldProvider
{
    protected function storageKind(): string { return 'string'; }
    protected function supportsOptions(): bool { return true; }
    protected function specificRules(FieldDefinition $field): array { return [['rule' => 'string'], ['rule' => 'in', 'value' => array_column($field->options, 'value')]]; }
    protected function validateSpecificDefinition(FieldDefinition $field): void
    {
        if ($field->options === []) throw new InvalidFieldDefinitionException('field_definition.options_required');
        $seen = [];
        foreach ($field->options as $option) {
            if (! isset($option['value'], $option['label']) || ! is_string($option['value']) || ! is_array($option['label']) || ! preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $option['value']) || isset($seen[$option['value']])) throw new InvalidFieldDefinitionException('field_definition.invalid_option');
            $seen[$option['value']] = true;
        }
    }
    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        if ($empty = $this->emptyOrFail($value, $field)) return $empty;
        if (! is_string($value) || ! in_array($value, array_column($field->options, 'value'), true)) throw new FieldNormalizationException('field_normalization.invalid_option');
        return $this->result($value, $field);
    }
}
