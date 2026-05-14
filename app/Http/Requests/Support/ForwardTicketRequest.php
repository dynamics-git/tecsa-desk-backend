<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ForwardTicketRequest extends FormRequest
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
            'mode' => ['required', Rule::in(['team', 'external', 'task'])],
            'to' => ['nullable', 'required_if:mode,external', 'email:rfc', 'max:254'],
            'teamId' => ['nullable', 'required_if:mode,team', 'string', 'max:120'],
            'comment' => ['nullable', 'string', 'max:10000'],
            'note' => ['nullable', 'string', 'max:10000'],
            'includeAttachments' => ['nullable', 'boolean'],
            'attachmentIds' => ['nullable', 'array', 'max:25'],
            'attachmentIds.*' => ['required', 'string', 'distinct', 'max:64'],
            'createLinkedTask' => ['nullable', 'boolean'],
            'taskTitle' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn (): bool => $this->input('mode') === 'task' || $this->boolean('createLinkedTask')),
            ],
            'taskAssignee' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'mode' => $this->input('mode', $this->has('to') ? 'external' : null),
            'comment' => $this->input('comment', $this->input('note')),
        ]);
    }
}
