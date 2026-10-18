<?php

namespace Modules\Glitter\FormBuilder\Fields;

use Modules\Glitter\FormBuilder\Contracts\Fields\FieldProvider;

final class FieldDefinitionFactory
{
    /** @return array<string, mixed> */
    public function template(FieldProvider $provider, string $locale, string $label): array
    {
        $metadata = $provider->metadata();
        $type = $provider->type();

        return [
            'key' => '',
            'type' => $type,
            'provider' => $provider->providerId(),
            'contractVersion' => $provider->contractVersion(),
            'label' => [$locale => $label],
            'description' => [],
            'required' => $type === 'consent',
            'default' => $type === 'consent' ? false : null,
            'options' => [],
            'validation' => [],
            'config' => $this->configurationDefaults($type),
            'sensitive' => $metadata->sensitiveByDefault,
            'indexable' => false,
            'exportable' => true,
        ];
    }

    /** @return array<string, mixed> */
    private function configurationDefaults(string $type): array
    {
        return match ($type) {
            'text', 'textarea' => ['trim' => true],
            'hidden' => ['source' => 'schema_default'],
            'consent' => ['statementId' => ConsentDefinitionDefaults::STATEMENT_ID],
            default => [],
        };
    }
}
