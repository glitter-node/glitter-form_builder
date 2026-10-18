<?php

namespace Modules\Glitter\FormBuilder\Fields\ValueObjects;

final readonly class FieldValidationContext
{
    public function __construct(
        public string $formPublicId,
        public int $formVersionRevision,
        public string $fieldKey,
        public string $locale,
        public string $accessMode,
        public bool $authenticated,
        public string $siteTimezone,
        public string $schemaVersion = '1.0',
    ) {}

    /** @return array<string, string|int|bool> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
