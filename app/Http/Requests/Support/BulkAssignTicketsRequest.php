<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class BulkAssignTicketsRequest extends FormRequest
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
            'agent' => ['required', 'string', 'max:80'],
        ];
    }
}
