<?php

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertCustomerUserAccessRequest extends FormRequest
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
            'id' => ['sometimes', 'required', 'string', 'max:100'],
            'userId' => ['required', 'string', 'max:100'],
            'userName' => ['nullable', 'string', 'max:255'],
            'userEmail' => ['nullable', 'string', 'max:255'],
            'customerId' => ['required', 'string', 'max:100'],
            'customerName' => ['nullable', 'string', 'max:255'],
            'accessLevel' => ['required', Rule::in(['OwnTickets', 'CompanyTickets', 'Admin'])],
            'canCreateTicket' => ['nullable', 'boolean'],
            'canViewAttachments' => ['nullable', 'boolean'],
            'canReply' => ['nullable', 'boolean'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->input('id'),
            'userId' => $this->input('userId'),
            'userName' => $this->input('userName'),
            'userEmail' => $this->input('userEmail'),
            'customerId' => $this->input('customerId'),
            'customerName' => $this->input('customerName'),
            'accessLevel' => $this->input('accessLevel'),
            'canCreateTicket' => $this->toBoolean($this->input('canCreateTicket', false)),
            'canViewAttachments' => $this->toBoolean($this->input('canViewAttachments', false)),
            'canReply' => $this->toBoolean($this->input('canReply', false)),
            'isActive' => $this->toBoolean($this->input('isActive', $this->input('active', true))),
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
