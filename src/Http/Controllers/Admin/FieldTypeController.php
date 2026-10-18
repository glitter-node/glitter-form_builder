<?php

namespace Modules\Glitter\FormBuilder\Http\Controllers\Admin;

use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Modules\Glitter\FormBuilder\Services\FieldTypeCatalog;

final class FieldTypeController extends AdminBaseController
{
    public function __construct(private FieldTypeCatalog $catalog) { parent::__construct(); }

    public function index(): JsonResponse
    {
        return $this->success('glitter-form_builder::messages.field_types.fetch_success', [
            'fieldTypes' => $this->catalog->all((string) app()->getLocale()),
        ]);
    }
}
