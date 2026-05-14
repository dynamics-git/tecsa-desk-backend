<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:120', 'unique:support_categories,id'],
            'name' => ['required', 'string', 'max:120'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
