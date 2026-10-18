<?php

namespace Modules\Glitter\FormBuilder\Http\Requests\Admin;

use App\Rules\LocaleRequiredTranslatable;
use App\Rules\TranslatableField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\FormBuilder\Enums\FormAccessMode;
use Modules\Glitter\FormBuilder\Enums\RetentionMode;
use Modules\Glitter\FormBuilder\Models\Form;

class StoreFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Form::class, 'slug')],
            'title' => ['required', 'array', new LocaleRequiredTranslatable(maxLength: 255)],
            'description' => ['nullable', new TranslatableField(maxLength: 2000)],
            'draft_schema' => ['required', 'array'],
            'draft_schema.schemaVersion' => ['required', 'string', 'in:1.0'],
            'draft_schema.fields' => ['present', 'array'],
            'access_mode' => ['nullable', Rule::enum(FormAccessMode::class)],
            'retention_mode' => ['nullable', Rule::enum(RetentionMode::class)],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:36500', 'required_if:retention_mode,days', 'prohibited_unless:retention_mode,days'],
            'retention_acknowledged' => ['nullable', 'boolean'],
        ];
    }
}
