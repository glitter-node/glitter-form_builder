<?php

namespace Modules\Glitter\FormBuilder\Http\Resources;

use App\Http\Resources\BaseApiCollection;
use App\Http\Resources\Traits\HasAbilityCheck;
use Illuminate\Http\Request;

class FormCollection extends BaseApiCollection
{
    use HasAbilityCheck;

    public $collects = FormResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(fn (FormResource $resource) => $resource->toListArray($request)),
            'meta' => [
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
            ],
            'abilities' => $this->resolveAbilitiesFromMap($this->abilityMap(), $request->user()),
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
}
