<?php

namespace Modules\Glitter\FormBuilder\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\FormBuilder\Enums\FormStatus;

class FormListRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(FormStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort_by' => ['nullable', 'in:created_at,updated_at,published_at,slug'],
            'sort_order' => ['nullable', 'in:asc,desc'],
        ];
    }
}
