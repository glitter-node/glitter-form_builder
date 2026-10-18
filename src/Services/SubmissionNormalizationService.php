<?php

namespace Modules\Glitter\FormBuilder\Services;

use Carbon\CarbonImmutable;
use Modules\Glitter\FormBuilder\DTO\NormalizedSubmission;
use Modules\Glitter\FormBuilder\DTO\SubmissionContext;
use Modules\Glitter\FormBuilder\Exceptions\Fields\FieldNormalizationException;
use Modules\Glitter\FormBuilder\Exceptions\SubmissionValidationException;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldDefinition;
use Modules\Glitter\FormBuilder\Fields\ValueObjects\FieldValidationContext;

final class SubmissionNormalizationService
{
    public function __construct(private readonly FieldTypeRegistry $registry) {}

    /** @param array<string, mixed> $snapshot @param array<string, mixed> $input */
    public function normalize(array $snapshot, array $input, SubmissionContext $context): NormalizedSubmission
    {
        $form = $snapshot['form'] ?? null;
        $fields = $snapshot['fields'] ?? null;
        if (($snapshot['schemaVersion'] ?? null) !== '1.0' || ! is_array($form) || ! is_array($fields) || ! array_is_list($fields)) {
            throw new SubmissionValidationException(['form' => [$this->message('submission.unavailable')]]);
        }

        $definitions = [];
        foreach ($fields as $raw) {
            if (! is_array($raw) || ! is_string($raw['key'] ?? null)) {
                throw new SubmissionValidationException(['form' => [$this->message('submission.unavailable')]]);
            }
            $definitions[$raw['key']] = $raw;
        }
        $unknown = array_diff(array_keys($input), array_keys($definitions));
        if ($unknown !== []) {
            throw new SubmissionValidationException(array_fill_keys(array_map(fn ($key) => 'fields.'.$key, $unknown), [$this->message('submission.unknown_field')]));
        }

        $payload = [];
        $indexes = [];
        $errors = [];
        foreach (array_values($fields) as $position => $raw) {
            $definition = $this->definition($raw);
            try {
                $provider = $this->registry->resolve($definition->type);
                $this->registry->validateCompatibility($definition->contractVersion, ['normalize', 'serialize'], $provider);
                if ($provider->providerId() !== $definition->providerId) {
                    throw new \DomainException('provider mismatch');
                }
                $validationContext = new FieldValidationContext(
                    (string) ($form['publicId'] ?? ''),
                    (int) ($form['revision'] ?? 0),
                    $definition->key,
                    $context->locale ?? 'en',
                    (string) ($form['accessMode'] ?? ''),
                    $context->actorType !== 'guest',
                    $context->timezone ?? 'UTC',
                );
                $clientValue = $definition->type === 'hidden' ? null : ($input[$definition->key] ?? null);
                $result = $provider->normalize($clientValue, $definition, $validationContext);
                $serialized = $provider->serialize($result);
                if ($definition->type === 'consent') {
                    $serialized = [
                        'statementId' => (string) ($definition->configuration['statementId'] ?? ''),
                        'accepted' => true,
                        'acceptedAt' => CarbonImmutable::now('UTC')->toIso8601ZuluString(),
                        'fieldKey' => $definition->key,
                        'revision' => (int) ($form['revision'] ?? 0),
                    ];
                }
                $payload[$definition->key] = $serialized;

                $index = $provider->projectIndex($result, $definition);
                if ($index !== null && ! $definition->sensitive && $definition->indexable) {
                    $indexes[] = $this->indexRow($definition, $index->type, $index->value, $position);
                }
            } catch (FieldNormalizationException) {
                $errors['fields.'.$definition->key][] = $this->message('submission.invalid_field');
            } catch (\Throwable) {
                $errors['fields.'.$definition->key][] = $this->message('submission.unavailable');
            }
        }

        if ($errors !== []) {
            throw new SubmissionValidationException($errors);
        }

        return new NormalizedSubmission($payload, $indexes);
    }

    /** @param array<string, mixed> $raw */
    private function definition(array $raw): FieldDefinition
    {
        return new FieldDefinition(
            key: (string) ($raw['key'] ?? ''),
            type: (string) ($raw['type'] ?? ''),
            label: is_array($raw['label'] ?? null) ? $raw['label'] : [],
            description: is_array($raw['description'] ?? null) ? $raw['description'] : [],
            required: (bool) ($raw['required'] ?? false),
            defaultValue: $raw['default'] ?? null,
            options: is_array($raw['options'] ?? null) ? $raw['options'] : [],
            validation: is_array($raw['validation'] ?? null) ? $raw['validation'] : [],
            sensitive: (bool) ($raw['sensitive'] ?? false),
            indexable: (bool) ($raw['indexable'] ?? false),
            exportable: (bool) ($raw['exportable'] ?? true),
            providerId: (string) ($raw['provider'] ?? ''),
            contractVersion: (string) ($raw['contractVersion'] ?? ''),
            configuration: is_array($raw['config'] ?? null) ? $raw['config'] : [],
        );
    }

    /** @return array<string, mixed> */
    private function indexRow(FieldDefinition $field, string $kind, string|bool $value, int $position): array
    {
        $valueKind = match ($kind) {
            'string' => 'text',
            'decimal' => 'number',
            default => $kind,
        };
        $row = [
            'field_key' => $field->key, 'field_type' => $field->type, 'value_kind' => $valueKind,
            'value_text' => null, 'value_number' => null, 'value_boolean' => null,
            'value_date' => null, 'value_datetime' => null, 'value_hash' => null,
            'is_sensitive' => false, 'is_indexable' => true, 'position' => $position,
        ];
        match ($kind) {
            'number', 'decimal' => $row['value_number'] = (string) $value,
            'boolean' => $row['value_boolean'] = (bool) $value,
            'date' => $row['value_date'] = (string) $value,
            'datetime' => $row['value_datetime'] = str_replace(['T', 'Z'], [' ', ''], (string) $value),
            default => $row['value_text'] = (string) $value,
        };
        $row['value_hash'] = hash('sha256', (string) $value);

        return $row;
    }

    private function message(string $key): string
    {
        $translationKey = 'glitter-form_builder::messages.'.$key;

        return function_exists('app') && app()->bound('translator') ? __($translationKey) : $translationKey;
    }
}
