<?php

namespace Modules\Glitter\FormBuilder\Schema;

use Modules\Glitter\FormBuilder\Exceptions\Fields\IncompatibleFieldContractException;
use Modules\Glitter\FormBuilder\Exceptions\Fields\UnknownFieldTypeException;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Rendering\PublicRendererPolicy;
use Modules\Glitter\FormBuilder\Schema\Internal\SchemaIssueCollector;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\FormSchema;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;
use Throwable;

final class PublishSchemaValidator
{
    public function __construct(
        private readonly FormSchemaValidator $structuralValidator,
        private readonly FieldTypeRegistry $registry,
        private readonly PublicRendererPolicy $rendererPolicy = new PublicRendererPolicy,
    ) {}

    /** @param list<string> $supportedLocales */
    public function validate(FormSchema $schema, string $defaultLocale, array $supportedLocales): SchemaValidationResult
    {
        $structural = $this->structuralValidator->validate($schema, $defaultLocale, $supportedLocales, true);
        $issues = new SchemaIssueCollector;
        foreach ($structural->issues() as $issue) {
            $issues->append($issue);
        }

        foreach ($schema->fields as $index => $field) {
            $base = "fields.{$index}";
            $type = $field['type'] ?? null;
            if (! is_string($type) || $type === '') {
                continue;
            }

            try {
                $provider = $this->registry->resolve($type);
            } catch (UnknownFieldTypeException) {
                $issues->error('field.provider_unresolved', "{$base}.type");

                continue;
            }

            try {
                $contractVersion = is_string($field['contractVersion'] ?? null) ? $field['contractVersion'] : '';
                $this->registry->validateCompatibility($contractVersion, ['normalize', 'serialize'], $provider);
                if (($field['provider'] ?? null) !== $provider->providerId() || $contractVersion !== $provider->contractVersion()) {
                    throw new IncompatibleFieldContractException('field_contract.snapshot_mismatch');
                }
            } catch (IncompatibleFieldContractException) {
                $issues->error('field.provider_incompatible', "{$base}.contractVersion");

                continue;
            }

            if (! $this->canCreateDefinition($field)) {
                continue;
            }
            $definitionValid = true;
            try {
                $provider->validateDefinition($this->definition($field));
            } catch (Throwable) {
                $definitionValid = false;
                $issues->error('field.definition_invalid', $base);
            }

            if ($definitionValid && ! $this->rendererPolicy->supports($provider)) {
                $issues->error('field.renderer_unavailable', "{$base}.type");
            }
        }

        return $issues->result();
    }

    /** @param array<string, mixed> $field */
    private function canCreateDefinition(array $field): bool
    {
        return is_string($field['key'] ?? null)
            && is_string($field['type'] ?? null)
            && is_string($field['provider'] ?? null)
            && is_string($field['contractVersion'] ?? null)
            && is_array($field['label'] ?? null)
            && is_bool($field['required'] ?? null)
            && is_bool($field['sensitive'] ?? null)
            && is_bool($field['indexable'] ?? null)
            && is_bool($field['exportable'] ?? null)
            && is_array($field['validation'] ?? null)
            && is_array($field['config'] ?? null);
    }

    /** @param array<string, mixed> $field */
    private function definition(array $field): FieldDefinition
    {
        return new FieldDefinition(
            key: $field['key'],
            type: $field['type'],
            label: $field['label'],
            description: is_array($field['description'] ?? null) ? $field['description'] : [],
            required: $field['required'],
            defaultValue: $field['default'] ?? null,
            options: is_array($field['options'] ?? null) ? $field['options'] : [],
            validation: $field['validation'],
            sensitive: $field['sensitive'],
            indexable: $field['indexable'],
            exportable: $field['exportable'],
            providerId: $field['provider'],
            contractVersion: $field['contractVersion'],
            configuration: $field['config'],
        );
    }
}
