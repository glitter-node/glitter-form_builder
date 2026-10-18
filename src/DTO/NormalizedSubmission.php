<?php

namespace Modules\Glitter\FormBuilder\DTO;

final readonly class NormalizedSubmission
{
    /** @param array<string, mixed> $payload @param list<array<string, mixed>> $indexValues */
    public function __construct(public array $payload, public array $indexValues) {}
}
