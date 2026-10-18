<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class TextareaFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'textarea'; }
    protected function storageKind(): string { return 'string'; }
    protected function configurationKeys(): array { return ['trim']; }
    protected function validationKeys(): array { return ['minLength', 'maxLength']; }
    protected function specificRules(FieldDefinition $field): array { return [['rule' => 'string'], ['rule' => 'plain_text']]; }

    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        if ($empty = $this->emptyOrFail($value, $field)) return $empty;
        if (! is_string($value)) throw new FieldNormalizationException('field_normalization.invalid_textarea');
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if ($field->configuration['trim'] ?? false) $value = trim($value);
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if (isset($field->validation['minLength']) && $length < $field->validation['minLength']) throw new FieldNormalizationException('field_normalization.too_short');
        if (isset($field->validation['maxLength']) && $length > $field->validation['maxLength']) throw new FieldNormalizationException('field_normalization.too_long');
        return $this->result($value, $field);
    }
}
