<?php

namespace App\Http\Requests\Support;

use App\Support\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateTicketRequest extends FormRequest
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
            'subject' => ['required', 'string', 'max:255'],
            'customer' => ['required', 'string', 'max:120'],
            'requester' => ['required', 'string', 'max:120'],
            'team' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:120'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'message' => ['nullable', 'string', 'max:10000'],
            'body' => ['nullable', 'string', 'max:10000'],
            'attachmentIds' => ['nullable', 'array', 'max:25'],
            'attachmentIds.*' => ['required', 'string', 'distinct', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'message' => $this->input('message', $this->input('body')),
        ]);
    }
}
