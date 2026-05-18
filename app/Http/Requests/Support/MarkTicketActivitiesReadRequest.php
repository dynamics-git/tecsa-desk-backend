<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class MarkTicketActivitiesReadRequest extends FormRequest
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
            'activityIds' => ['required', 'array', 'min:1', 'max:500'],
            'activityIds.*' => ['required', 'string', 'distinct', 'max:32', 'exists:support_ticket_activities,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'activityIds' => $this->input('activityIds', $this->input('activity_ids', [])),
        ]);
    }
}
