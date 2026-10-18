<?php

namespace Modules\Glitter\FormBuilder\Fields\ValueObjects;

final readonly class FieldIndexValue
{
    public function __construct(public string $type, public string|bool $value) {}

    /** @return array{type: string, value: string|bool} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'value' => $this->value];
    }
}
