<?php

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPermissionRoleRequest extends FormRequest
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
        $visibility = ['All', 'Team', 'Assigned', 'TeamAndAssigned', 'Customer', 'Own'];

        return [
            'id' => ['sometimes', 'required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'string', 'max:100'],
            'user_email' => ['nullable', 'string', 'max:255'],
            'user_type' => ['nullable', Rule::in(['Internal', 'Customer'])],
            'ticket_visibility' => ['nullable', Rule::in($visibility)],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['string'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['string'],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->input('id', $this->input('role_id')),
            'name' => $this->input('name', $this->input('role_name', $this->input('role'))),
            'user_id' => $this->input('user_id', $this->input('userId')),
            'user_email' => $this->input('user_email', $this->input('userEmail')),
            'user_type' => $this->input('user_type', $this->input('userType')),
            'ticket_visibility' => $this->input('ticket_visibility', $this->input('ticketVisibility', $this->input('visibility_mode'))),
            'permissions' => $this->normalizeArray($this->input('permissions', $this->input('permission_keys', []))),
            'user_ids' => $this->normalizeArray($this->input('user_ids', $this->input('userIds', $this->input('role_user_ids', [])))),
            'team_ids' => $this->normalizeArray($this->input('team_ids', $this->input('teamIds', $this->input('team_scope_ids', [])))),
            'customer_ids' => $this->normalizeArray($this->input('customer_ids', $this->input('customerIds', $this->input('customer_scope_ids', [])))),
            'is_active' => $this->toBoolean($this->input('is_active', $this->input('isActive', $this->input('active', true)))),
            'is_admin' => $this->toBoolean($this->input('is_admin', $this->input('isAdmin', false))),
        ]);
    }

    /**
     * @param  mixed  $value
     * @return array<int, string>
     */
    private function normalizeArray($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($v) => is_scalar($v) ? trim((string) $v) : '', $value), fn ($v) => $v !== ''));
        }

        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map('trim', explode(',', $value))));
        }

        return [];
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
