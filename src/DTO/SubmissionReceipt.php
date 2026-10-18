<?php

namespace Modules\Glitter\FormBuilder\DTO;

final readonly class SubmissionReceipt
{
    public function __construct(
        public string $uuid,
        public string $status,
        public string $receivedAt,
        public string $formSlug,
        public int $revision,
        public bool $idempotentReplay,
    ) {}
}
