<?php

namespace Modules\Glitter\FormBuilder\Rendering;

use DomainException;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;
use Modules\Glitter\FormBuilder\Rendering\ValueObjects\PublicFormSchema;
use Throwable;

final class FormLayoutProjector
{
    public function __construct(
        private readonly FieldTypeRegistry $registry,
        private readonly PublicRendererPolicy $rendererPolicy = new PublicRendererPolicy,
    ) {}

    /** @param array<string, mixed> $snapshot */
    public function project(array $snapshot): PublicFormSchema
    {
        $schemaVersion = $snapshot['schemaVersion'] ?? null;
        $form = $snapshot['form'] ?? null;
        $fields = $snapshot['fields'] ?? null;
        $publishedAt = $snapshot['publishedAt'] ?? null;

        if ($schemaVersion !== '1.0' || ! is_array($form) || ! is_array($fields) || ! array_is_list($fields) || ! is_string($publishedAt)) {
            throw new DomainException('public_schema.invalid_snapshot');
        }

        $publicFields = [];
        $layoutFields = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                throw new DomainException('public_schema.invalid_field');
            }

            $type = $field['type'] ?? null;
            $version = $field['contractVersion'] ?? null;
            if (! is_string($type) || ! is_string($version)) {
                throw new DomainException('public_schema.unsupported_field');
            }

            try {
                $provider = $this->registry->resolve($type);
                $this->registry->validateCompatibility($version, ['renderer'], $provider);
            } catch (Throwable $exception) {
                throw new DomainException('public_schema.provider_unavailable', 0, $exception);
            }
            if (($field['provider'] ?? null) !== $provider->providerId() || $version !== $provider->contractVersion()) {
                throw new DomainException('public_schema.provider_mismatch');
            }
            if (! $this->rendererPolicy->supports($provider)) {
                throw new DomainException('public_schema.renderer_unavailable');
            }

            $safe = $this->publicField($field);
            $publicFields[] = $safe;
            if ($type !== 'hidden') {
                $layoutFields[] = [
                    'key' => $safe['key'],
                    'type' => $type,
                    'component' => $this->rendererPolicy->component($type),
                    'label' => $safe['label'],
                    'description' => $safe['description'],
                    'required' => $safe['required'],
                    'options' => $safe['options'],
                    'inputType' => $this->inputType($type),
                ];
            }
        }

        return new PublicFormSchema($schemaVersion, $this->publicForm($form), $publicFields, $layoutFields, $publishedAt);
    }

    /** @param array<string, mixed> $form @return array<string, mixed> */
    private function publicForm(array $form): array
    {
        $allowed = array_intersect_key($form, array_flip(['publicId', 'slug', 'revision', 'accessMode', 'title', 'description']));
        $description = $allowed['description'] ?? null;
        if (! is_string($allowed['publicId'] ?? null)
            || ! is_string($allowed['slug'] ?? null)
            || ! is_int($allowed['revision'] ?? null)
            || ! is_string($allowed['accessMode'] ?? null)
            || ! is_array($allowed['title'] ?? null)
            || ($description !== null && ! is_array($description))) {
            throw new DomainException('public_schema.invalid_form');
        }

        $allowed['description'] = $description ?? [];

        return $allowed;
    }

    /** @param array<string, mixed> $field @return array<string, mixed> */
    private function publicField(array $field): array
    {
        $safe = array_intersect_key($field, array_flip([
            'key', 'type', 'provider', 'contractVersion', 'label', 'description', 'required',
            'options', 'validation', 'sensitive', 'indexable', 'exportable',
        ]));
        foreach (['key', 'type', 'provider', 'contractVersion'] as $key) {
            if (! is_string($safe[$key] ?? null)) {
                throw new DomainException('public_schema.invalid_field');
            }
        }
        if (! is_array($safe['label'] ?? null) || ! is_bool($safe['required'] ?? null)) {
            throw new DomainException('public_schema.invalid_field');
        }

        $safe['description'] = is_array($safe['description'] ?? null) ? $safe['description'] : [];
        $safe['options'] = is_array($safe['options'] ?? null) && array_is_list($safe['options']) ? $safe['options'] : [];
        $safe['validation'] = is_array($safe['validation'] ?? null) ? $safe['validation'] : [];

        if ($safe['type'] === 'consent') {
            $configuration = $field['config'] ?? null;
            $statementId = is_array($configuration) ? ($configuration['statementId'] ?? null) : null;
            if (! is_string($statementId) || ! preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $statementId)) {
                throw new DomainException('public_schema.invalid_field');
            }
            $safe['config'] = ['statementId' => $statementId];
        }

        return $safe;
    }

    private function inputType(string $type): string
    {
        return match ($type) {
            'email' => 'email',
            'number' => 'text',
            'date' => 'date',
            'datetime' => 'datetime-local',
            default => 'text',
        };
    }
}
