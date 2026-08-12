<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class EmailFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'email'; }
    protected function storageKind(): string { return 'string'; }
    protected function validationKeys(): array { return ['maxLength']; }
    protected function sensitiveByDefault(): bool { return true; }
    protected function specificRules(FieldDefinition $field): array { return [['rule' => 'string'], ['rule' => 'email'], ['rule' => 'max_length', 'value' => $field->validation['maxLength'] ?? 254]]; }

    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        if ($empty = $this->emptyOrFail($value, $field)) return $empty;
        if (! is_string($value)) throw new FieldNormalizationException('field_normalization.invalid_email');
        $value = trim($value);
        $max = $field->validation['maxLength'] ?? 254;
        if (strlen($value) > $max || filter_var($value, FILTER_VALIDATE_EMAIL) === false) throw new FieldNormalizationException('field_normalization.invalid_email');
        return $this->result($value, $field);
    }
}
