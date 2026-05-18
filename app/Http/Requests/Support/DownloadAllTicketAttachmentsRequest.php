<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class DownloadAllTicketAttachmentsRequest extends FormRequest
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
            'attachmentIds' => ['nullable', 'array', 'max:100'],
            'attachmentIds.*' => ['required', 'string', 'distinct', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'attachmentIds' => $this->input('attachmentIds', $this->input('attachment_ids', [])),
        ]);
    }
}
