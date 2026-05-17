<?php

namespace App\Http\Requests\Setup;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSupportUserScopeRequest extends FormRequest
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
            'user_id' => ['required', 'string', 'max:100'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'user_email' => ['nullable', 'string', 'max:255'],
            'visibility_mode' => ['required', Rule::in($visibility)],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['string'],
            'queue_ids' => ['nullable', 'array'],
            'queue_ids.*' => ['string'],
            'customer_ids' => ['nullable', 'array'],
            'customer_ids.*' => ['string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->input('id'),
            'user_id' => $this->input('user_id', $this->input('userId')),
            'user_name' => $this->input('user_name', $this->input('userName')),
            'user_email' => $this->input('user_email', $this->input('userEmail')),
            'visibility_mode' => $this->input('visibility_mode', $this->input('visibilityMode', $this->input('ticket_visibility', $this->input('ticketVisibility', 'Own')))),
            'team_ids' => $this->normalizeArray($this->input('team_ids', $this->input('teamIds', []))),
            'queue_ids' => $this->normalizeArray($this->input('queue_ids', $this->input('queueIds', []))),
            'customer_ids' => $this->normalizeArray($this->input('customer_ids', $this->input('customerIds', []))),
            'is_active' => $this->toBoolean($this->input('is_active', $this->input('isActive', $this->input('active', true)))),
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
