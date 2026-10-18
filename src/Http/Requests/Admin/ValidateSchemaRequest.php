<?php

namespace Modules\Glitter\FormBuilder\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ValidateSchemaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'schema' => ['required', 'array'],
            'defaultLocale' => ['required', 'string', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'supportedLocales' => ['required', 'array', 'min:1', 'max:20'],
            'supportedLocales.*' => ['string', 'distinct', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'mode' => ['required', Rule::in(['draft', 'publish-preview'])],
        ];
    }
}
