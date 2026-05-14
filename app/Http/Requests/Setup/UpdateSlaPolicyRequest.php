<?php

namespace App\Http\Requests\Setup;

use App\Support\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'firstResponseMinutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'resolutionMinutes' => ['sometimes', 'required', 'integer', 'min:1'],
            'isActive' => ['sometimes', 'boolean'],
        ];
    }
}
