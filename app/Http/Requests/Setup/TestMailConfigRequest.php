<?php

namespace App\Http\Requests\Setup;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestMailConfigRequest extends FormRequest
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
            'to' => ['required', 'email:rfc', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'mailer' => ['nullable', Rule::in(['smtp', 'log', 'array', 'sendmail', 'ses', 'postmark', 'resend'])],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['nullable', Rule::in(['ssl', 'tls'])],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'fromAddress' => ['nullable', 'email:rfc', 'max:255'],
            'fromName' => ['nullable', 'string', 'max:255'],
            'replyToAddress' => ['nullable', 'email:rfc', 'max:255'],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:120'],
            'isActive' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'fromAddress' => $this->input('fromAddress', $this->input('from_address')),
            'fromName' => $this->input('fromName', $this->input('from_name')),
            'replyToAddress' => $this->input('replyToAddress', $this->input('reply_to_address')),
            'isActive' => $this->input('isActive', $this->input('is_active', true)),
        ]);
    }
}
