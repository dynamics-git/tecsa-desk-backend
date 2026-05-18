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
            'userId' => ['nullable', 'string', 'max:100'],
            'userEmail' => ['nullable', 'string', 'max:255'],
            'userType' => ['required', Rule::in(['Internal', 'Customer'])],
            'ticketVisibility' => ['required', Rule::in($visibility)],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string'],
            'userIds' => ['nullable', 'array'],
            'userIds.*' => ['string'],
            'teamIds' => ['nullable', 'array'],
            'teamIds.*' => ['string'],
            'customerIds' => ['nullable', 'array'],
            'customerIds.*' => ['string'],
            'isActive' => ['nullable', 'boolean'],
            'isAdmin' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->input('id', $this->input('roleId')),
            'name' => $this->input('name', $this->input('roleName', $this->input('role'))),
            'userId' => $this->input('userId'),
            'userEmail' => $this->input('userEmail'),
            'userType' => $this->input('userType'),
            'ticketVisibility' => $this->input('ticketVisibility', $this->input('visibilityMode')),
            'permissions' => $this->normalizeArray($this->input('permissions', $this->input('permissionKeys', []))),
            'userIds' => $this->normalizeArray($this->input('userIds', $this->input('roleUserIds', []))),
            'teamIds' => $this->normalizeArray($this->input('teamIds', $this->input('teamScopeIds', []))),
            'customerIds' => $this->normalizeArray($this->input('customerIds', $this->input('customerScopeIds', []))),
            'isActive' => $this->toBoolean($this->input('isActive', $this->input('active', true))),
            'isAdmin' => $this->toBoolean($this->input('isAdmin', false)),
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
