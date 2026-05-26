<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class ProcessInboundEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $token = (string) config('services.support_inbound.token', '');

        return $token === '' || hash_equals($token, (string) $this->header('X-Support-Inbound-Token', ''));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'messageId' => ['required', 'string', 'max:255'],
            'inReplyTo' => ['nullable', 'string', 'max:255'],
            'references' => ['nullable'],
            'from' => ['required', 'string', 'max:255'],
            'fromName' => ['nullable', 'string', 'max:255'],
            'replyTo' => ['nullable', 'string', 'max:255'],
            'deliveredTo' => ['nullable', 'string', 'max:255'],
            'envelopeTo' => ['nullable', 'string', 'max:255'],
            'recipient' => ['nullable', 'string', 'max:255'],
            'to' => ['nullable', 'array', 'max:100'],
            'to.*' => ['string', 'max:255'],
            'cc' => ['nullable', 'array', 'max:100'],
            'cc.*' => ['string', 'max:255'],
            'bcc' => ['nullable', 'array', 'max:100'],
            'bcc.*' => ['string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'textBody' => ['nullable', 'string', 'max:500000'],
            'htmlBody' => ['nullable', 'string', 'max:500000'],
            'createdAt' => ['nullable', 'date'],
            'attachments' => ['nullable', 'array', 'max:50'],
            'attachments.*.fileName' => ['nullable', 'string', 'max:255'],
            'attachments.*.name' => ['nullable', 'string', 'max:255'],
            'attachments.*.mimeType' => ['nullable', 'string', 'max:120'],
            'attachments.*.size' => ['nullable', 'integer', 'min:0'],
            'attachments.*.contentBase64' => ['nullable', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'messageId' => $this->input('messageId', $this->input('message_id')),
            'inReplyTo' => $this->input('inReplyTo', $this->input('in_reply_to')),
            'fromName' => $this->input('fromName', $this->input('from_name')),
            'replyTo' => $this->input('replyTo', $this->input('reply_to')),
            'deliveredTo' => $this->input('deliveredTo', $this->input('delivered_to')),
            'envelopeTo' => $this->input('envelopeTo', $this->input('envelope_to')),
            'recipient' => $this->input('recipient', $this->input('recipient_email')),
            'textBody' => $this->input('textBody', $this->input('text_body')),
            'htmlBody' => $this->input('htmlBody', $this->input('html_body')),
            'createdAt' => $this->input('createdAt', $this->input('created_at')),
            'attachments' => $this->input('attachments', []),
        ]);
    }
}
