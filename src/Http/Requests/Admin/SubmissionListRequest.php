<?php

namespace Modules\Glitter\FormBuilder\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\FormBuilder\Enums\SubmissionStatus;

final class SubmissionListRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(SubmissionStatus::class)],
            'form' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
