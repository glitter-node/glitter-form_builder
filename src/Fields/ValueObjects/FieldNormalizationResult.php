<?php

namespace Modules\Glitter\FormBuilder\Fields\ValueObjects;

final readonly class FieldNormalizationResult
{
    /** @param list<string> $diagnostics */
    public function __construct(
        public mixed $normalizedValue,
        public mixed $serializedValue,
        public string $redactedDisplayValue,
        public bool $empty,
        public string $storageKind,
        public string $redactionKind,
        public array $diagnostics = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
