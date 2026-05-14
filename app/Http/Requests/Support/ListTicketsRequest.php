<?php

namespace App\Http\Requests\Support;

use App\Support\Enums\TicketPriority;
use App\Support\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListTicketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'queue' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
            'status' => ['nullable', Rule::enum(TicketStatus::class)],
            'agent' => ['nullable', 'string', 'max:80'],
            'sort' => ['nullable', Rule::in([
                'updated_desc',
                'updated_asc',
                'priority_desc',
                'priority_asc',
                'status_asc',
                'customer_asc',
                'subject_asc',
            ])],
            'page' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
