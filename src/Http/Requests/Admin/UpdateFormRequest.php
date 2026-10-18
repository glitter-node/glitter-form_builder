<?php

namespace Modules\Glitter\FormBuilder\Http\Requests\Admin;

use App\Rules\LocaleRequiredTranslatable;
use App\Rules\TranslatableField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Glitter\FormBuilder\Enums\FormAccessMode;
use Modules\Glitter\FormBuilder\Enums\RetentionMode;
use Modules\Glitter\FormBuilder\Models\Form;

class UpdateFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $form = $this->route('form');

        return [
            'slug' => ['sometimes', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique(Form::class, 'slug')->ignore($form?->getKey())],
            'title' => ['sometimes', 'required', 'array', new LocaleRequiredTranslatable(maxLength: 255)],
            'description' => ['sometimes', 'nullable', new TranslatableField(maxLength: 2000)],
            'draft_schema' => ['sometimes', 'required', 'array'],
            'draft_schema.schemaVersion' => ['required_with:draft_schema', 'string', 'in:1.0'],
            'draft_schema.fields' => ['required_with:draft_schema', 'array'],
            'access_mode' => ['sometimes', Rule::enum(FormAccessMode::class)],
            'retention_mode' => ['sometimes', Rule::enum(RetentionMode::class)],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:36500', 'required_if:retention_mode,days', 'prohibited_unless:retention_mode,days'],
            'retention_acknowledged' => ['sometimes', 'boolean'],
        ];
    }
}
