<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class SendTicketEmailRequest extends FormRequest
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
            'to' => ['required', 'array', 'min:1', 'max:50'],
            'to.*' => ['required', 'email:rfc', 'max:255'],
            'cc' => ['nullable', 'array', 'max:50'],
            'cc.*' => ['required', 'email:rfc', 'max:255'],
            'bcc' => ['nullable', 'array', 'max:50'],
            'bcc.*' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'htmlBody' => ['nullable', 'string', 'max:200000'],
            'textBody' => ['nullable', 'string', 'max:200000'],
            'attachmentIds' => ['nullable', 'array', 'max:50'],
            'attachmentIds.*' => ['required', 'string', 'distinct', 'max:64'],
            'parentActivityId' => ['nullable', 'string', 'max:32', 'exists:support_ticket_activities,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'htmlBody' => $this->input('htmlBody', $this->input('html_body')),
            'textBody' => $this->input('textBody', $this->input('text_body')),
            'attachmentIds' => $this->input('attachmentIds', $this->input('attachment_ids', [])),
            'parentActivityId' => $this->input('parentActivityId', $this->input('parent_activity_id')),
        ]);
    }
}
