<?php

namespace Modules\Glitter\FormBuilder\Fields;

final class ConsentDefinitionDefaults
{
    public const STATEMENT_ID = 'consent';

    /** @param array<string, mixed> $schema
     *  @return array<string, mixed>
     */
    public static function normalizeLegacySchema(array $schema): array
    {
        if (! isset($schema['fields']) || ! is_array($schema['fields'])) {
            return $schema;
        }

        foreach ($schema['fields'] as $index => $field) {
            if (! is_array($field)
                || ($field['type'] ?? null) !== 'consent'
                || ($field['provider'] ?? 'glitter-form_builder') !== 'glitter-form_builder'
                || ! isset($field['config'])
                || ! is_array($field['config'])
                || ! array_key_exists('statementId', $field['config'])
                || ! in_array($field['config']['statementId'], [null, ''], true)) {
                continue;
            }

            $field['config']['statementId'] = self::STATEMENT_ID;
            $schema['fields'][$index] = $field;
        }

        return $schema;
    }
}
