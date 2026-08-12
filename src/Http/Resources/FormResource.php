<?php

namespace Modules\Glitter\FormBuilder\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;
use Modules\Glitter\FormBuilder\Fields\ConsentDefinitionDefaults;

class FormResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'draft_schema' => ConsentDefinitionDefaults::normalizeLegacySchema($this->draft_schema),
            'access_mode' => $this->access_mode->value,
            'retention' => [
                'mode' => $this->retention_mode->value,
                'days' => $this->retention_days,
                'acknowledged_at' => $this->retention_acknowledged_at?->toISOString(),
                'automatic_enforcement' => false,
            ],
            'current_published_version' => $this->whenLoaded('currentPublishedVersion', fn () => $this->currentPublishedVersion ? new FormVersionResource($this->currentPublishedVersion) : null),
            'published_at' => $this->published_at?->toISOString(),
            ...$this->formatTimestamps(),
            ...$this->resourceMeta($request),
        ];
    }

    protected function abilityMap(): array
    {
        return [
            'can_create' => 'glitter-form_builder.forms.create',
            'can_update' => 'glitter-form_builder.forms.update',
            'can_delete' => 'glitter-form_builder.forms.delete',
            'can_publish' => 'glitter-form_builder.forms.publish',
        ];
    }

    protected function ownerField(): ?string { return 'created_by'; }

    public function toListArray(?Request $request = null): array
    {
        $request ??= request();

        return [
            'uuid' => $this->uuid,
            'slug' => $this->slug,
            'title' => $this->getLocalizedField('title'),
            'status' => $this->status->value,
            'access_mode' => $this->access_mode->value,
            'current_revision' => $this->currentPublishedVersion?->revision,
            'published_at' => $this->published_at?->toISOString(),
            ...$this->formatTimestamps(),
            ...$this->resourceMeta($request),
        ];
    }
}
