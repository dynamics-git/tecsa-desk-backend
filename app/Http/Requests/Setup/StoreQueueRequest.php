<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;

class StoreQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:120', 'unique:support_queues,id'],
            'name' => ['required', 'string', 'max:120'],
            'teamId' => ['nullable', 'string', 'max:120', 'exists:support_teams,id'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
