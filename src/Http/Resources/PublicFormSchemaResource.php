<?php

namespace Modules\Glitter\FormBuilder\Http\Resources;

use App\Http\Resources\BaseApiResource;
use Illuminate\Http\Request;
use Modules\Glitter\FormBuilder\Rendering\ValueObjects\PublicFormSchema;

final class PublicFormSchemaResource extends BaseApiResource
{
    public function __construct(PublicFormSchema $resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PublicFormSchema $schema */
        $schema = $this->resource;

        return $schema->toArray();
    }
}
