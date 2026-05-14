<?php

namespace App\Http\Requests\Support;

use App\Support\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class BulkUpdateStatusRequest extends FormRequest
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
            'ticketIds' => ['required', 'array', 'min:1', 'max:100'],
            'ticketIds.*' => ['required', 'string', 'distinct', 'max:32'],
            'status' => ['required', Rule::enum(TicketStatus::class)],
        ];
    }
}
