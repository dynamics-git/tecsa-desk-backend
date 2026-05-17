<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportPermissionRoleRequest extends FormRequest
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
            'user_id' => ['nullable', 'string', 'max:100', 'required_without:user_email'],
            'user_email' => ['nullable', 'email', 'max:255', 'required_without:user_id'],
            'name' => ['required', 'string', 'max:255'],
            'user_type' => ['nullable', Rule::in(['Internal', 'Customer'])],
            'ticket_visibility' => ['nullable', Rule::in(['All', 'Team', 'Assigned', 'TeamAndAssigned', 'Customer', 'Own'])],
            'permissions' => ['required', 'array', 'max:200'],
            'permissions.*' => ['required', 'string', 'max:120', 'distinct'],
            'user_ids' => ['nullable', 'array', 'max:200'],
            'user_ids.*' => ['required_with:user_ids', 'string', 'max:120', 'distinct'],
            'team_ids' => ['nullable', 'array', 'max:200'],
            'team_ids.*' => ['required_with:team_ids', 'string', 'max:120', 'distinct'],
            'customer_ids' => ['nullable', 'array', 'max:200'],
            'customer_ids.*' => ['required_with:customer_ids', 'string', 'max:120', 'distinct'],
            'is_active' => ['nullable', 'boolean'],
            'is_admin' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->input('user_id', $this->input('userId')),
            'user_email' => $this->input('user_email', $this->input('userEmail')),
            'name' => $this->input('name', $this->input('role', '')),
            'user_type' => $this->input('user_type', $this->input('userType')),
            'ticket_visibility' => $this->input('ticket_visibility', $this->input('ticketVisibility', $this->input('visibility_mode'))),
            'permissions' => $this->normalizeArray($this->input('permissions', $this->input('permission_keys', []))),
            'user_ids' => $this->normalizeArray($this->input('user_ids', $this->input('role_user_ids', []))),
            'team_ids' => $this->normalizeArray($this->input('team_ids', $this->input('team_scope_ids', $this->input('teamIds', [])))),
            'customer_ids' => $this->normalizeArray($this->input('customer_ids', $this->input('customer_scope_ids', $this->input('customerIds', [])))),
            'is_active' => $this->toBoolean($this->input('is_active', $this->input('active', true))),
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
