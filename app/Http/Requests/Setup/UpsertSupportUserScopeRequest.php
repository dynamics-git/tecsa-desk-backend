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
            'userId' => ['required', 'string', 'max:100'],
            'userName' => ['nullable', 'string', 'max:255'],
            'userEmail' => ['nullable', 'string', 'max:255'],
            'visibilityMode' => ['required', Rule::in($visibility)],
            'teamIds' => ['nullable', 'array'],
            'teamIds.*' => ['string'],
            'queueIds' => ['nullable', 'array'],
            'queueIds.*' => ['string'],
            'customerIds' => ['nullable', 'array'],
            'customerIds.*' => ['string'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->input('id'),
            'userId' => $this->input('userId'),
            'userName' => $this->input('userName'),
            'userEmail' => $this->normalizeEmail($this->input('userEmail')),
            'visibilityMode' => $this->input('visibilityMode', $this->input('ticketVisibility', 'Own')),
            'teamIds' => $this->normalizeArray($this->input('teamIds', [])),
            'queueIds' => $this->normalizeArray($this->input('queueIds', [])),
            'customerIds' => $this->normalizeArray($this->input('customerIds', [])),
            'isActive' => $this->toBoolean($this->input('isActive', $this->input('active', true))),
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

    private function normalizeEmail(mixed $value): ?string
    {
        $email = strtolower(trim((string) $value));

        return $email === '' ? null : $email;
    }
}
