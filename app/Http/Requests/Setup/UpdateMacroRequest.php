<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMacroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:160'],
            'body' => ['sometimes', 'required', 'string', 'max:10000'],
            'visibility' => ['nullable', Rule::in(['public', 'internal'])],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
