<?php

namespace Modules\Glitter\FormBuilder\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class SubmitFormRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fields' => ['required', 'array', 'max:100'],
            'context' => ['sometimes', 'array'],
            'context.locale' => ['sometimes', 'string', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/', 'max:12'],
            'context.timezone' => ['sometimes', 'string', 'timezone', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $key = $this->header('Idempotency-Key');
        if ($key !== null) {
            $this->merge(['_idempotency_key' => $key]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $length = (int) $this->server('CONTENT_LENGTH', 0);
            if ($length > 262144 || strlen($this->getContent()) > 262144) {
                $validator->errors()->add('fields', __('glitter-form_builder::messages.submission.payload_too_large'));
            }
            $unknownTop = array_diff(array_keys($this->all()), ['fields', 'context', '_idempotency_key']);
            $unknownContext = array_diff(array_keys((array) $this->input('context', [])), ['locale', 'timezone']);
            if ($unknownTop !== [] || $unknownContext !== []) {
                $validator->errors()->add('context', __('glitter-form_builder::messages.submission.invalid_envelope'));
            }
            $key = $this->input('_idempotency_key');
            if ($key !== null && (! is_string($key) || ! preg_match('/^[\x21-\x7E]{16,128}$/', $key))) {
                $validator->errors()->add('Idempotency-Key', __('glitter-form_builder::messages.submission.invalid_idempotency_key'));
            }
            foreach ((array) $this->input('fields', []) as $field => $value) {
                if (! is_string($field) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $field) || is_array($value) || is_object($value)) {
                    $validator->errors()->add('fields.'.(string) $field, __('glitter-form_builder::messages.submission.invalid_field'));
                }
            }
        });
    }

    public function idempotencyKey(): ?string
    {
        $key = $this->input('_idempotency_key');

        return is_string($key) ? $key : null;
    }
}
