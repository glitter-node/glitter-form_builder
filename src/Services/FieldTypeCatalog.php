<?php

namespace Modules\Glitter\FormBuilder\Services;

use Illuminate\Contracts\Translation\Translator;
use Modules\Glitter\FormBuilder\Fields\FieldDefinitionFactory;
use Modules\Glitter\FormBuilder\Fields\FieldTypeRegistry;

final class FieldTypeCatalog
{
    public function __construct(
        private FieldTypeRegistry $registry,
        private FieldDefinitionFactory $definitions,
        private Translator $translator,
    ) {}

    /** @return list<array<string, mixed>> */
    public function all(string $locale): array
    {
        $items = [];
        foreach ($this->registry->all() as $provider) {
            $metadata = $provider->metadata();
            $renderer = $provider->rendererDescriptor();
            $items[] = [
                'type' => $metadata->type,
                'provider' => $metadata->providerId,
                'contractVersion' => $metadata->contractVersion,
                'name' => $this->translator->get($metadata->nameKey, [], $locale),
                'description' => $this->translator->get($metadata->descriptionKey, [], $locale),
                'category' => 'basic',
                'capabilities' => [
                    'options' => $metadata->supportsOptions,
                    'default' => $metadata->supportsDefault,
                    'index' => $metadata->supportsIndex,
                ],
                'renderer' => [
                    'identifier' => $renderer->identifier,
                    'contractMajor' => $renderer->contractMajor,
                    'available' => $renderer->available,
                ],
                'definitionTemplate' => $this->definitions->template(
                    $provider,
                    $locale,
                    $this->translator->get($metadata->nameKey, [], $locale),
                ),
            ];
        }

        return $items;
    }
}
