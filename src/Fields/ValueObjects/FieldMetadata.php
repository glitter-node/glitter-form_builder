<?php

namespace Modules\Glitter\FormBuilder\Fields\ValueObjects;

final readonly class FieldMetadata
{
    /** @param list<string> $capabilities */
    public function __construct(
        public string $type,
        public string $providerId,
        public string $contractVersion,
        public string $nameKey,
        public string $descriptionKey,
        public string $storageKind,
        public bool $supportsOptions,
        public bool $supportsDefault,
        public bool $supportsIndex,
        public bool $sensitiveByDefault,
        public string $redactionKind,
        public array $capabilities = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
