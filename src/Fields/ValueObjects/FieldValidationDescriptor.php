<?php

namespace Modules\Glitter\FormBuilder\Fields\ValueObjects;

final readonly class FieldValidationDescriptor
{
    /** @param list<array<string, bool|int|string|array<int, string>>> $rules */
    public function __construct(
        public array $rules,
        public string $attribute,
        public string $messageKey,
        public bool $failClosed = true,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
