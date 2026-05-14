<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'teamId' => ['nullable', 'string', 'max:120', 'exists:support_teams,id'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
