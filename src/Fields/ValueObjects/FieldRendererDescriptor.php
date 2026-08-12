<?php

namespace Modules\Glitter\FormBuilder\Fields\ValueObjects;

final readonly class FieldRendererDescriptor
{
    /** @param array<string, bool|int|string|array<int, string>> $safeMetadata */
    public function __construct(
        public string $identifier,
        public int $contractMajor,
        public array $safeMetadata = [],
        public bool $available = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
