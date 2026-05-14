<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UploadAttachmentRequest extends FormRequest
{
    public const MAX_FILE_KB = 10240;

    public const ALLOWED_MIMES = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp',
        'txt',
        'csv',
        'doc',
        'docx',
        'xls',
        'xlsx',
    ];

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
            'file' => ['required', 'file', 'max:'.self::MAX_FILE_KB, 'mimes:'.implode(',', self::ALLOWED_MIMES)],
            'visibility' => ['required', Rule::in(['public', 'internal'])],
            'ticketId' => ['nullable', 'string', 'max:32', 'exists:support_tickets,id'],
            'customer' => ['nullable', 'string', 'max:120'],
            'requester' => ['nullable', 'string', 'max:120'],
        ];
    }
}
