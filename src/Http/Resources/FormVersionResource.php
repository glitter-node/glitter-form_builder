<?php

namespace Modules\Glitter\FormBuilder\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;

class FormVersionResource extends BaseApiResource
{
    public function toArray(Request $request): array
    {
        return [
            'revision' => $this->revision,
            'schema_version' => $this->schema_version,
            'snapshot' => $this->snapshot,
            'published_at' => $this->published_at?->toISOString(),
        ];
    }
}
