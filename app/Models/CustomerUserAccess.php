<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerUserAccess extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'customer_id',
        'customer_name',
        'access_level',
        'can_create_ticket',
        'can_view_attachments',
        'can_reply',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'can_create_ticket' => 'boolean',
            'can_view_attachments' => 'boolean',
            'can_reply' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
