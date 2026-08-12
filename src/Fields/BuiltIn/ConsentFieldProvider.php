<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\InvalidFieldDefinitionException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class ConsentFieldProvider extends AbstractFieldProvider
{
    public function type(): string { return 'consent'; }
    protected function storageKind(): string { return 'boolean'; }
    protected function indexType(): string { return 'boolean'; }
    protected function configurationKeys(): array { return ['statementId', 'policyVersion']; }
    protected function validationKeys(): array { return ['accepted']; }
    protected function specificRules(FieldDefinition $field): array { return [['rule' => 'accepted']]; }
    protected function validateSpecificDefinition(FieldDefinition $field): void
    {
        if (! $field->required || $field->defaultValue === true || ($field->validation['accepted'] ?? true) !== true || ! isset($field->configuration['statementId']) || ! is_string($field->configuration['statementId']) || ! preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $field->configuration['statementId'])) throw new InvalidFieldDefinitionException('field_definition.invalid_consent');
    }
    protected function validateDefault(FieldDefinition $field): void
    {
        if ($field->defaultValue !== null && $field->defaultValue !== false) throw new InvalidFieldDefinitionException('field_definition.consent_default_forbidden');
    }
    public function normalize(mixed $value, FieldDefinition $field, FieldValidationContext $context): FieldNormalizationResult
    {
        $accepted = in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
        if (! $accepted) throw new FieldNormalizationException('field_normalization.consent_required');
        return $this->result(true, $field);
    }
}
