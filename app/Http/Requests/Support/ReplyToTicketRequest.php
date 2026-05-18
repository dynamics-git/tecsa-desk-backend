<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReplyToTicketRequest extends FormRequest
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
            'message' => ['nullable', 'string', 'min:1', 'max:10000', 'required_without:htmlBody'],
            'htmlBody' => ['nullable', 'string', 'required_without:message'],
            'isInternalNote' => ['required', 'boolean'],
            'attachmentIds' => ['nullable', 'array', 'max:25'],
            'attachmentIds.*' => ['required', 'string', 'distinct', 'max:64'],
            'parentActivityId' => ['nullable', 'string', 'max:32', 'exists:support_ticket_activities,id'],
            'mentions' => ['nullable', 'array', 'max:25'],
            'mentions.*.id' => ['required_with:mentions', 'string', 'max:120'],
            'mentions.*.kind' => ['required_with:mentions', Rule::in(['user', 'team'])],
            'mentions.*.display' => ['required_with:mentions', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'htmlBody' => $this->input('htmlBody', $this->input('html_body')),
            'attachmentIds' => $this->input('attachmentIds', $this->input('attachment_ids', [])),
            'parentActivityId' => $this->input('parentActivityId', $this->input('parent_activity_id')),
            'isInternalNote' => $this->input('isInternalNote', $this->input('is_internal_note')),
        ]);
    }
}
