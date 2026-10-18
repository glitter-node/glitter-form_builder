<?php

namespace Modules\Glitter\FormBuilder\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;
use Modules\Glitter\FormBuilder\Enums\SubmissionStatus;

class AdminSubmissionResource extends BaseApiResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->summary($request);
    }

    /** @return array<string, mixed> */
    protected function summary(Request $request): array
    {
        $form = $this->resource->form;
        $version = $this->resource->formVersion;

        return [
            'uuid' => $this->resource->uuid,
            'status' => $this->resource->status instanceof SubmissionStatus ? $this->resource->status->value : $this->resource->status,
            'form' => ['uuid' => $form?->uuid, 'slug' => $form?->slug, 'title' => $form?->title, 'revision' => $version?->revision],
            'actor' => ['type' => $this->resource->actor_type->value, 'user_uuid' => $this->resource->submittedUser?->uuid],
            'received_at' => $this->resource->received_at?->toIso8601String(),
            'retention_due_at' => $this->resource->retention_due_at?->toIso8601String(),
            'automatic_purge' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function detail(Request $request): array
    {
        $base = $this->summary($request);
        $snapshot = $this->resource->formVersion?->snapshot;
        $payload = is_array($this->resource->normalized_payload) ? $this->resource->normalized_payload : [];
        $fields = [];
        foreach (is_array($snapshot['fields'] ?? null) ? $snapshot['fields'] : [] as $field) {
            if (! is_array($field) || ! is_string($field['key'] ?? null)) continue;
            $key = $field['key'];
            $sensitive = (bool) ($field['sensitive'] ?? false);
            $value = $payload[$key] ?? null;
            $fields[] = [
                'key' => $key,
                'type' => (string) ($field['type'] ?? ''),
                'label' => is_array($field['label'] ?? null) ? $field['label'] : [],
                'sensitive' => $sensitive,
                'redacted' => $sensitive,
                'display_value' => $sensitive ? '••••••' : $this->display($value),
            ];
        }

        return [...$base, 'fields' => $fields];
    }

    private function display(mixed $value): string|bool|int|null
    {
        if (is_array($value)) {
            return ($value['accepted'] ?? false) === true ? 'accepted' : null;
        }

        return is_string($value) || is_bool($value) || is_int($value) || $value === null ? $value : (string) $value;
    }
}
