<?php

namespace Modules\Glitter\FormBuilder\Fields\BuiltIn;

use JsonException;
use Modules\Glitter\FormBuilder\Contracts\Fields\FieldProvider;
use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\InvalidFieldDefinitionException;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldIndexValue;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldMetadata;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldNormalizationResult;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldRendererDescriptor;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationDescriptor;

abstract class AbstractFieldProvider implements FieldProvider
{
    public function providerId(): string { return 'glitter-form_builder'; }
    public function contractVersion(): string { return self::CONTRACT_VERSION; }

    public function metadata(): FieldMetadata
    {
        return new FieldMetadata(
            type: $this->type(),
            providerId: $this->providerId(),
            contractVersion: $this->contractVersion(),
            nameKey: "glitter-form_builder::fields.types.{$this->type()}.name",
            descriptionKey: "glitter-form_builder::fields.types.{$this->type()}.description",
            storageKind: $this->storageKind(),
            supportsOptions: $this->supportsOptions(),
            supportsDefault: true,
            supportsIndex: $this->supportsIndex(),
            sensitiveByDefault: $this->sensitiveByDefault(),
            redactionKind: $this->redactionKind(),
            capabilities: array_merge(
                ['normalize', 'serialize', 'display', 'export', 'renderer'],
                $this->supportsIndex() ? ['index'] : [],
            ),
        );
    }

    public function configurationSchema(): array
    {
        return [
            'configuration' => array_values($this->configurationKeys()),
            'validation' => array_values($this->validationKeys()),
            'unknownProperties' => 'reject',
        ];
    }

    public function validateDefinition(FieldDefinition $field): void
    {
        if ($field->type !== $this->type() || $field->providerId !== $this->providerId() || $field->contractVersion !== $this->contractVersion()) {
            throw new InvalidFieldDefinitionException('field_definition.identity_mismatch');
        }
        if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field->key)) {
            throw new InvalidFieldDefinitionException('field_definition.invalid_key');
        }
        if ($field->sensitive && $field->indexable) {
            throw new InvalidFieldDefinitionException('field_definition.sensitive_index_forbidden');
        }
        $this->rejectUnknownKeys($field->configuration, $this->configurationKeys(), 'configuration');
        $this->rejectUnknownKeys($field->validation, $this->validationKeys(), 'validation');
        $this->assertJsonSafe([$field->configuration, $field->validation, $field->options, $field->rendererConfiguration]);
        if (! $this->supportsOptions() && $field->options !== []) {
            throw new InvalidFieldDefinitionException('field_definition.options_not_supported');
        }
        if ($field->indexable && ! $this->supportsIndex()) {
            throw new InvalidFieldDefinitionException('field_definition.index_not_supported');
        }
        $this->validateTranslations($field->label);
        $this->validateSpecificDefinition($field);
        $this->validateDefault($field);
    }

    public function validationDescriptor(FieldDefinition $field, FieldValidationContext $context): FieldValidationDescriptor
    {
        $this->validateDefinition($field);
        $rules = $field->required ? [['rule' => 'required']] : [['rule' => 'nullable']];

        return new FieldValidationDescriptor(
            array_merge($rules, $this->specificRules($field)),
            $field->key,
            'glitter-form_builder::fields.validation.invalid',
        );
    }

    public function serialize(FieldNormalizationResult $result): mixed { return $result->serializedValue; }

    public function display(FieldNormalizationResult $result, string $locale): string
    {
        return $result->redactionKind === 'none' ? $this->stringify($result->normalizedValue) : $result->redactedDisplayValue;
    }

    public function export(FieldNormalizationResult $result, string $locale): string|int|bool|null
    {
        $value = $result->serializedValue;
        if (is_array($value)) {
            return implode(',', array_map(static fn (mixed $item): string => (string) $item, $value));
        }

        return is_string($value) || is_int($value) || is_bool($value) || $value === null ? $value : null;
    }

    public function projectIndex(FieldNormalizationResult $result, FieldDefinition $field): ?FieldIndexValue
    {
        if (! $field->indexable || $field->sensitive || $result->empty || ! $this->supportsIndex()) {
            return null;
        }

        return new FieldIndexValue($this->indexType(), $result->serializedValue);
    }

    public function rendererDescriptor(): FieldRendererDescriptor
    {
        return new FieldRendererDescriptor(
            "glitter-form_builder:{$this->type()}",
            1,
            ['type' => $this->type(), 'output' => 'g7-layout'],
            true,
        );
    }

    /** @return list<string> */
    protected function configurationKeys(): array { return []; }
    /** @return list<string> */
    protected function validationKeys(): array { return []; }
    /** @return list<array<string, bool|int|string|array<int, string>>> */
    protected function specificRules(FieldDefinition $field): array { return []; }
    protected function validateSpecificDefinition(FieldDefinition $field): void {}
    protected function validateDefault(FieldDefinition $field): void
    {
        if ($field->defaultValue !== null) {
            $this->normalize($field->defaultValue, $field, self::definitionContext($field));
        }
    }
    protected function supportsOptions(): bool { return false; }
    protected function supportsIndex(): bool { return true; }
    protected function sensitiveByDefault(): bool { return false; }
    protected function redactionKind(): string { return $this->sensitiveByDefault() ? 'masked' : 'none'; }
    protected function indexType(): string { return $this->storageKind(); }
    abstract protected function storageKind(): string;

    protected function result(mixed $value, FieldDefinition $field, ?string $storageKind = null): FieldNormalizationResult
    {
        $empty = $value === null || $value === '' || $value === [];
        $redaction = $field->sensitive ? $this->redactionKind() : 'none';

        return new FieldNormalizationResult(
            $value,
            $value,
            $redaction === 'none' ? $this->stringify($value) : '••••••',
            $empty,
            $storageKind ?? $this->storageKind(),
            $redaction,
        );
    }

    protected function emptyOrFail(mixed $value, FieldDefinition $field): ?FieldNormalizationResult
    {
        if ($value !== null && $value !== '') {
            return null;
        }
        if ($field->required) {
            throw new FieldNormalizationException('field_normalization.required');
        }

        return $this->result(null, $field);
    }

    /** @param array<string, mixed> $values @param list<string> $allowed */
    private function rejectUnknownKeys(array $values, array $allowed, string $area): void
    {
        $unknown = array_diff(array_keys($values), $allowed);
        if ($unknown !== []) {
            throw new InvalidFieldDefinitionException("field_definition.unknown_{$area}_key");
        }
    }

    /** @param array<string, string> $translations */
    private function validateTranslations(array $translations): void
    {
        if ($translations === []) {
            throw new InvalidFieldDefinitionException('field_definition.label_required');
        }
        foreach ($translations as $locale => $value) {
            if (! preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) || trim($value) === '') {
                throw new InvalidFieldDefinitionException('field_definition.invalid_translation');
            }
        }
    }

    private function assertJsonSafe(array $values): void
    {
        $walk = function (mixed $value) use (&$walk): void {
            if (is_array($value)) {
                foreach ($value as $item) $walk($item);
                return;
            }
            if ($value !== null && ! is_string($value) && ! is_int($value) && ! is_float($value) && ! is_bool($value)) {
                throw new InvalidFieldDefinitionException('field_definition.non_json_safe_value');
            }
        };

        try {
            $walk($values);
            json_encode($values, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidFieldDefinitionException('field_definition.non_json_safe_value');
        }
    }

    protected function stringify(mixed $value): string
    {
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_array($value)) return implode(', ', array_map('strval', $value));
        return $value === null ? '' : (string) $value;
    }

    private static function definitionContext(FieldDefinition $field): FieldValidationContext
    {
        return new FieldValidationContext('00000000-0000-0000-0000-000000000000', 0, $field->key, 'en', 'admin_only', true, 'UTC');
    }
}
