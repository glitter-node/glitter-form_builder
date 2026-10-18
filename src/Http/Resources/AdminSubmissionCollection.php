<?php

namespace Modules\Glitter\FormBuilder\Http\Resources;

use App\Http\Resources\BaseApiCollection;
use Illuminate\Http\Request;

final class AdminSubmissionCollection extends BaseApiCollection
{
    public $collects = AdminSubmissionResource::class;

    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(fn (AdminSubmissionResource $resource) => $resource->toArray($request)),
            'meta' => [
                'current_page' => $this->resource->currentPage(), 'last_page' => $this->resource->lastPage(),
                'per_page' => $this->resource->perPage(), 'total' => $this->resource->total(),
            ],
        ];
    }
}
