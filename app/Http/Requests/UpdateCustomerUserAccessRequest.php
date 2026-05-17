<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerUserAccessRequest extends FormRequest
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
            'user_id' => ['sometimes', 'required', 'string', 'max:100'],
            'user_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'user_email' => ['sometimes', 'nullable', 'string', 'max:255'],
            'customer_id' => ['sometimes', 'required', 'string', 'max:120'],
            'customer_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'access_level' => ['sometimes', 'required', Rule::in(['OwnTickets', 'CompanyTickets', 'Admin'])],
            'can_create_ticket' => ['sometimes', 'boolean'],
            'can_view_attachments' => ['sometimes', 'boolean'],
            'can_reply' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->has('user_id') ? $this->input('user_id') : $this->input('userId'),
            'user_name' => $this->has('user_name') ? $this->input('user_name') : $this->input('userName'),
            'user_email' => $this->has('user_email') ? $this->input('user_email') : $this->input('userEmail'),
            'customer_id' => $this->has('customer_id') ? $this->input('customer_id') : $this->input('customerId'),
            'customer_name' => $this->has('customer_name') ? $this->input('customer_name') : $this->input('customerName'),
            'access_level' => $this->has('access_level') ? $this->input('access_level') : $this->input('accessLevel'),
            'can_create_ticket' => $this->has('can_create_ticket')
                ? $this->toBoolean($this->input('can_create_ticket'))
                : ($this->has('canCreateTicket') ? $this->toBoolean($this->input('canCreateTicket')) : null),
            'can_view_attachments' => $this->has('can_view_attachments')
                ? $this->toBoolean($this->input('can_view_attachments'))
                : ($this->has('canViewAttachments') ? $this->toBoolean($this->input('canViewAttachments')) : null),
            'can_reply' => $this->has('can_reply')
                ? $this->toBoolean($this->input('can_reply'))
                : ($this->has('canReply') ? $this->toBoolean($this->input('canReply')) : null),
            'is_active' => $this->has('is_active')
                ? $this->toBoolean($this->input('is_active'))
                : ($this->has('active') ? $this->toBoolean($this->input('active')) : null),
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
