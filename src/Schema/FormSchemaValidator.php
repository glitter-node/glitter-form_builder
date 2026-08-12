<?php

namespace Modules\Glitter\FormBuilder\Schema;

use Modules\Glitter\FormBuilder\Schema\Internal\SchemaIssueCollector;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\FormSchema;
use Modules\Glitter\FormBuilder\Schema\ValueObjects\SchemaValidationResult;

final class FormSchemaValidator
{
    public const SUPPORTED_VERSION = '1.0';
    private const TOP_LEVEL_KEYS = ['schemaVersion', 'form', 'fields', 'providerSnapshot', 'publishedAt'];
    private const FIELD_KEYS = ['key', 'type', 'provider', 'contractVersion', 'label', 'description', 'required', 'default', 'options', 'validation', 'config', 'sensitive', 'indexable', 'exportable'];
    private const RESERVED_FIELD_KEYS = ['id', 'uuid', 'form_id', 'submission_id', 'created_at', 'updated_at', 'deleted_at', 'metadata', 'abilities'];

    /** @param list<string> $supportedLocales */
    public function validate(FormSchema $schema, string $defaultLocale, array $supportedLocales, bool $forPublish): SchemaValidationResult
    {
        $issues = new SchemaIssueCollector;
        foreach ($schema->parsingIssues as $issue) $issues->append($issue);

        $this->validateVersion($schema, $issues);
        foreach ($schema->topLevelKeys as $key) {
            if (! in_array($key, self::TOP_LEVEL_KEYS, true)) $issues->error('schema.unknown_key', $key);
        }
        if ($schema->encodedBytes === null) {
            // Parser already added a safe JSON error.
        } elseif ($schema->encodedBytes > SchemaLimits::MAX_ENCODED_BYTES) {
            $issues->error('schema.too_large', '', ['limit' => SchemaLimits::MAX_ENCODED_BYTES]);
        }
        if (count($schema->fields) > SchemaLimits::MAX_FIELDS) {
            $issues->error('schema.too_many_fields', 'fields', ['limit' => SchemaLimits::MAX_FIELDS]);
        }
        [$depth, $nodes] = $this->measure($schema->fields);
        if ($depth > SchemaLimits::MAX_NESTING_DEPTH) $issues->error('schema.too_deep', 'fields', ['limit' => SchemaLimits::MAX_NESTING_DEPTH]);
        if ($nodes > SchemaLimits::MAX_NODES) $issues->error('schema.too_many_nodes', 'fields', ['limit' => SchemaLimits::MAX_NODES]);

        $seen = [];
        foreach ($schema->fields as $index => $field) {
            $base = "fields.{$index}";
            foreach (array_keys($field) as $key) {
                if (! in_array($key, self::FIELD_KEYS, true)) $issues->error('field.unknown_key', "{$base}.{$key}");
            }
            $key = $field['key'] ?? null;
            if (! is_string($key) || $key === '') {
                $issues->error('field.key_missing', "{$base}.key");
            } elseif (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key)) {
                $issues->error('field.key_invalid', "{$base}.key");
            } else {
                if (in_array($key, self::RESERVED_FIELD_KEYS, true)) $issues->error('field.key_reserved', "{$base}.key");
                if (isset($seen[$key])) $issues->error('field.key_duplicate', "{$base}.key");
                $seen[$key] = true;
            }
            $this->validateStringProperty($field, 'type', $base, $issues, '/^(?:[a-z][a-z0-9_]{0,63}|[a-z0-9][a-z0-9_-]{1,63}:[a-z][a-z0-9_-]{0,63})$/', 'field.type');
            $this->validateStringProperty($field, 'provider', $base, $issues, '/^[a-z0-9][a-z0-9_-]{1,63}$/', 'field.provider');
            $this->validateStringProperty($field, 'contractVersion', $base, $issues, '/^\d+\.\d+$/', 'field.contract_version');
            $this->validateLocaleMap($field['label'] ?? null, "{$base}.label", $defaultLocale, $supportedLocales, SchemaLimits::MAX_LABEL_LENGTH, true, $forPublish, $issues);
            if (array_key_exists('description', $field)) $this->validateLocaleMap($field['description'], "{$base}.description", $defaultLocale, $supportedLocales, SchemaLimits::MAX_DESCRIPTION_LENGTH, false, $forPublish, $issues);
            foreach (['required', 'sensitive', 'indexable', 'exportable'] as $property) {
                if (! array_key_exists($property, $field) || ! is_bool($field[$property])) $issues->error('field.property_invalid', "{$base}.{$property}", ['property' => $property]);
            }
            foreach (['config', 'validation'] as $property) {
                if (! array_key_exists($property, $field) || ! is_array($field[$property]) || array_is_list($field[$property]) && $field[$property] !== []) $issues->error("field.{$property}_invalid", "{$base}.{$property}");
            }
            if (isset($field['options'])) {
                if (! is_array($field['options']) || ! array_is_list($field['options'])) $issues->error('field.options_invalid', "{$base}.options");
                elseif (count($field['options']) > SchemaLimits::MAX_OPTIONS_PER_FIELD) $issues->error('field.too_many_options', "{$base}.options", ['limit' => SchemaLimits::MAX_OPTIONS_PER_FIELD]);
            }
            if (($field['sensitive'] ?? false) === true && ($field['indexable'] ?? false) === true) $issues->error('field.sensitive_index_forbidden', "{$base}.indexable");
        }

        return $issues->result();
    }

    private function validateVersion(FormSchema $schema, SchemaIssueCollector $issues): void
    {
        foreach ($schema->parsingIssues as $issue) {
            if ($issue->code === 'schema.version_malformed') return;
        }
        if ($schema->schemaVersion === null) { $issues->error('schema.version_missing', 'schemaVersion'); return; }
        if (! preg_match('/^\d+\.\d+$/', $schema->schemaVersion)) { $issues->error('schema.version_malformed', 'schemaVersion'); return; }
        if ($schema->schemaVersion !== self::SUPPORTED_VERSION) $issues->error('schema.version_unsupported', 'schemaVersion', ['supported' => self::SUPPORTED_VERSION]);
    }

    private function validateStringProperty(array $field, string $property, string $base, SchemaIssueCollector $issues, string $pattern, string $codePrefix): void
    {
        if (! isset($field[$property]) || ! is_string($field[$property])) { $issues->error("{$codePrefix}_missing", "{$base}.{$property}"); return; }
        if (! preg_match($pattern, $field[$property])) $issues->error("{$codePrefix}_invalid", "{$base}.{$property}");
    }

    /** @param list<string> $supportedLocales */
    private function validateLocaleMap(mixed $map, string $path, string $defaultLocale, array $supportedLocales, int $maxLength, bool $required, bool $forPublish, SchemaIssueCollector $issues): void
    {
        if ($map === [] && ! $required) return;
        if (! is_array($map) || array_is_list($map)) { $issues->error($required ? 'field.label_missing' : 'field.description_invalid', $path); return; }
        if (count($map) > SchemaLimits::MAX_LOCALES) $issues->error('field.too_many_locales', $path, ['limit' => SchemaLimits::MAX_LOCALES]);
        foreach ($map as $locale => $value) {
            if (! is_string($locale) || ! preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) || ! in_array($locale, $supportedLocales, true)) $issues->error('field.locale_unsupported', "{$path}.{$locale}");
            if (! is_string($value) || $this->length($value) > $maxLength) $issues->error('field.translation_invalid', "{$path}.{$locale}", ['limit' => $maxLength]);
        }
        if ($required && (! isset($map[$defaultLocale]) || ! is_string($map[$defaultLocale]) || trim($map[$defaultLocale]) === '')) {
            if ($forPublish) $issues->error('field.default_locale_missing', $path, ['locale' => $defaultLocale]);
            else $issues->warning('field.default_locale_missing', $path, ['locale' => $defaultLocale]);
        }
    }

    /** @return array{int, int} */
    private function measure(mixed $value, int $depth = 1): array
    {
        if (! is_array($value)) return [$depth, 1];
        $max = $depth; $nodes = 1;
        foreach ($value as $child) { [$childDepth, $childNodes] = $this->measure($child, $depth + 1); $max = max($max, $childDepth); $nodes += $childNodes; }
        return [$max, $nodes];
    }

    private function length(string $value): int { return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value); }
}
