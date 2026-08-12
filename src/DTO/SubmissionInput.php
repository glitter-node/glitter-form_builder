<?php

namespace Modules\Glitter\FormBuilder\DTO;

final readonly class SubmissionInput
{
    /** @param array<string, mixed> $fields */
    public function __construct(
        public array $fields,
        public SubmissionContext $context,
        public ?string $idempotencyKey,
    ) {}
}
