<?php

namespace Modules\Glitter\FormBuilder\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class SubmissionReceived implements ShouldDispatchAfterCommit
{
    public function __construct(
        public string $submissionUuid,
        public string $formUuid,
        public string $formSlug,
        public int $revision,
        public string $actorType,
        public ?string $submittedUserUuid,
        public string $receivedAt,
        public ?string $locale,
        public string $status,
    ) {}

    /** @return array<string, string|int|null> */
    public function payload(): array
    {
        return [
            'submission_uuid' => $this->submissionUuid,
            'form_uuid' => $this->formUuid,
            'form_slug' => $this->formSlug,
            'revision' => $this->revision,
            'actor_type' => $this->actorType,
            'submitted_user_uuid' => $this->submittedUserUuid,
            'received_at' => $this->receivedAt,
            'locale' => $this->locale,
            'status' => $this->status,
        ];
    }
}
