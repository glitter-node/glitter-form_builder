<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class CheckboxFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'checkbox'; }
    protected function storageKind(): string { return 'boolean'; }
    protected function indexType(): string { return 'boolean'; }
    protected function validationKeys(): array { return ['accepted']; }
    protected function specificRules(FieldDefinition $field): array { return [['rule' => 'boolean'], ['rule' => 'accepted', 'value' => (bool) ($field->validation['accepted'] ?? false)]]; }
    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        if ($value === null || $value === '') {
            if ($field->required) throw new FieldNormalizationException('field_normalization.required');
            return $this->result(false, $field);
        }
        $normalized = match (true) {
            $value === true, $value === 1, $value === '1', $value === 'true', $value === 'on' => true,
            $value === false, $value === 0, $value === '0', $value === 'false', $value === 'off' => false,
            default => throw new FieldNormalizationException('field_normalization.invalid_boolean'),
        };
        if (($field->validation['accepted'] ?? false) && ! $normalized) throw new FieldNormalizationException('field_normalization.acceptance_required');
        return $this->result($normalized, $field);
    }
}
