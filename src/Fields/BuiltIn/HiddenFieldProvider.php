<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Exceptions\Fields\InvalidFieldDefinitionException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class HiddenFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'hidden'; }
    protected function storageKind(): string { return 'string'; }
    protected function supportsIndex(): bool { return false; }
    protected function sensitiveByDefault(): bool { return true; }
    protected function configurationKeys(): array { return ['source']; }
    protected function validateSpecificDefinition(FieldDefinition $field): void
    {
        if (($field->configuration['source'] ?? 'schema_default') !== 'schema_default' || $field->defaultValue === null || (! is_string($field->defaultValue) && ! is_int($field->defaultValue) && ! is_bool($field->defaultValue))) throw new InvalidFieldDefinitionException('field_definition.invalid_hidden_source');
    }
    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        $serverValue = $field->defaultValue;
        return $this->result(is_int($serverValue) || is_bool($serverValue) ? (string) $serverValue : $serverValue, $field);
    }
}
