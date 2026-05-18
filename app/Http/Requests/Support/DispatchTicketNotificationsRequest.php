<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DispatchTicketNotificationsRequest extends FormRequest
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
            'eventTypes' => ['required', 'array', 'min:1', 'max:10'],
            'eventTypes.*' => ['required', Rule::in(['reply', 'email', 'forward', 'internal_mention'])],
            'activityId' => ['nullable', 'string', 'max:32', 'exists:support_ticket_activities,id'],
            'channels' => ['nullable', 'array', 'max:10'],
            'channels.*' => ['required', Rule::in(['email', 'in_app', 'push', 'webhook'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'eventTypes' => $this->input('eventTypes', $this->input('event_types', [])),
            'activityId' => $this->input('activityId', $this->input('activity_id')),
        ]);
    }
}
