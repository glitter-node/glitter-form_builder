<?php

namespace Modules\Glitter\FormBuilder\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PublishFormRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array { return []; }
}
