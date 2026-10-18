<?php

namespace Modules\Glitter\FormBuilder\DTO;

final readonly class SubmissionContext
{
    public function __construct(
        public string $actorType,
        public ?int $userId,
        public ?string $locale,
        public ?string $timezone,
        public string $source,
        public ?string $ipHash,
        public ?string $userAgentHash,
        public ?string $userUuid = null,
    ) {}
}
