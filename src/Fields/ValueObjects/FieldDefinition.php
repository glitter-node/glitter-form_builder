<?php

namespace Modules\Glitter\FormBuilder\Fields\ValueObjects;

final readonly class FieldDefinition
{
    /**
     * @param array<string, string> $label
     * @param array<string, string> $description
     * @param array<int, array{value: string, label: array<string, string>}> $options
     * @param array<string, mixed> $validation
     * @param array<string, mixed> $configuration
     * @param array<string, mixed> $rendererConfiguration
     */
    public function __construct(
        public string $key,
        public string $type,
        public array $label,
        public array $description = [],
        public bool $required = false,
        public mixed $defaultValue = null,
        public array $options = [],
        public array $validation = [],
        public bool $sensitive = false,
        public bool $indexable = false,
        public bool $exportable = true,
        public string $providerId = 'glitter-form_builder',
        public string $contractVersion = '1.0',
        public array $configuration = [],
        public array $rendererConfiguration = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
