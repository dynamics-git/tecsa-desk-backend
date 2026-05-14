<?php

namespace App\Http\Requests\Setup;

use App\Support\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSlaPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'max:120', 'unique:support_sla_policies,id'],
            'name' => ['required', 'string', 'max:120'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'firstResponseMinutes' => ['required', 'integer', 'min:1'],
            'resolutionMinutes' => ['required', 'integer', 'min:1'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }
}
