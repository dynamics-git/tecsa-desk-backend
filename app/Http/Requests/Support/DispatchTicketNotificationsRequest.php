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
            'event' => ['required', Rule::in(['reply', 'email', 'forward', 'internal_mention'])],
            'eventTypes' => ['sometimes', 'array', 'min:1', 'max:10'],
            'eventTypes.*' => ['required', Rule::in(['reply', 'email', 'forward', 'internal_mention'])],
            'activityId' => ['required', 'string', 'max:32', 'exists:support_ticket_activities,id'],
            'channels' => ['nullable', 'array', 'max:10'],
            'channels.*' => ['required', Rule::in(['email', 'in_app', 'push', 'webhook'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $eventTypesInput = $this->input('eventTypes', $this->input('event_types', []));
        $event = $this->input('event', $this->input('event_type'));

        if (($event === null || $event === '') && is_array($eventTypesInput) && $eventTypesInput !== []) {
            $event = (string) ($eventTypesInput[0] ?? '');
        }

        if (! is_array($eventTypesInput) || $eventTypesInput === []) {
            $eventTypesInput = $event !== null && $event !== '' ? [(string) $event] : [];
        }

        $this->merge([
            'event' => $event,
            'eventTypes' => $eventTypesInput,
            'activityId' => $this->input('activityId', $this->input('activity_id')),
        ]);
    }
}
