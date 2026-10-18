<?php

namespace Modules\Glitter\FormBuilder\Schema;

use JsonException;
use Modules\Glitter\FormBuilder\Schema\Enums\SchemaIssueSeverity;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\FormSchema;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationIssue;

final class FormSchemaParser
{
    public function parse(array $raw): FormSchema
    {
        $issues = [];
        $version = $raw['schemaVersion'] ?? null;
        if ($version !== null && ! is_string($version)) {
            $issues[] = $this->issue('schema.version_malformed', 'schemaVersion');
            $version = null;
        }

        $fields = $raw['fields'] ?? null;
        if (! is_array($fields) || ! array_is_list($fields)) {
            $issues[] = $this->issue(isset($raw['fields']) ? 'schema.fields_not_list' : 'schema.fields_missing', 'fields');
            $fields = [];
        } else {
            foreach ($fields as $index => $field) {
                if (! is_array($field)) {
                    $issues[] = $this->issue('field.invalid_type', "fields.{$index}");
                    $fields[$index] = [];
                } else {
                    $fields[$index] = array_merge([
                        'description' => [],
                        'default' => null,
                        'options' => [],
                        'validation' => [],
                        'config' => [],
                    ], $field);
                }
            }
        }

        $form = $raw['form'] ?? null;
        if ($form !== null && ! is_array($form)) {
            $issues[] = $this->issue('schema.form_invalid', 'form');
            $form = null;
        }
        $providerSnapshot = $raw['providerSnapshot'] ?? [];
        if (! is_array($providerSnapshot) || ! array_is_list($providerSnapshot)) {
            $issues[] = $this->issue('schema.provider_snapshot_invalid', 'providerSnapshot');
            $providerSnapshot = [];
        }
        $publishedAt = $raw['publishedAt'] ?? null;
        if ($publishedAt !== null && ! is_string($publishedAt)) {
            $issues[] = $this->issue('schema.published_at_invalid', 'publishedAt');
            $publishedAt = null;
        }

        $bytes = null;
        try {
            if (! $this->isJsonSafe($raw)) throw new JsonException('Non-data JSON value.');
            $encoded = json_encode($raw, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $bytes = strlen($encoded);
        } catch (JsonException) {
            $issues[] = $this->issue('schema.not_json_safe', '');
        }

        return new FormSchema(
            $version,
            array_values($fields),
            $form,
            array_values($providerSnapshot),
            $publishedAt,
            array_keys($raw),
            $bytes,
            $issues,
        );
    }

    private function isJsonSafe(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) if (! $this->isJsonSafe($item)) return false;
            return true;
        }

        return $value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value);
    }

    private function issue(string $code, string $path): SchemaValidationIssue
    {
        return new SchemaValidationIssue(
            $code,
            $path,
            'glitter-form_builder::schema.errors.'.str_replace('.', '_', $code),
            [],
            SchemaIssueSeverity::Error,
        );
    }
}
