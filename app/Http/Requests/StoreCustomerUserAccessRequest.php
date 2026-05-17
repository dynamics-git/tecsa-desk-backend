<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerUserAccessRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'max:100'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'user_email' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['required', 'string', 'max:120'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'access_level' => ['required', Rule::in(['OwnTickets', 'CompanyTickets', 'Admin'])],
            'can_create_ticket' => ['nullable', 'boolean'],
            'can_view_attachments' => ['nullable', 'boolean'],
            'can_reply' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->input('user_id', $this->input('userId')),
            'user_name' => $this->input('user_name', $this->input('userName')),
            'user_email' => $this->input('user_email', $this->input('userEmail')),
            'customer_id' => $this->input('customer_id', $this->input('customerId')),
            'customer_name' => $this->input('customer_name', $this->input('customerName')),
            'access_level' => $this->input('access_level', $this->input('accessLevel')),
            'can_create_ticket' => $this->toBoolean($this->input('can_create_ticket', $this->input('canCreateTicket', false))),
            'can_view_attachments' => $this->toBoolean($this->input('can_view_attachments', $this->input('canViewAttachments', false))),
            'can_reply' => $this->toBoolean($this->input('can_reply', $this->input('canReply', false))),
            'is_active' => $this->toBoolean($this->input('is_active', $this->input('active', true))),
        ]);
    }

    private function toBoolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value !== 0;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'active'], true);
    }
}
