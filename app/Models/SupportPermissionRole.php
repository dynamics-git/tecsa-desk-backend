<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportPermissionRole extends Model
{
    protected $fillable = [
        'user_id',
        'user_email',
        'user_type',
        'role',
        'permissions',
        'user_ids',
        'team_ids',
        'customer_ids',
        'ticket_visibility',
        'is_active',
        'is_admin',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
            'user_ids' => 'array',
            'team_ids' => 'array',
            'customer_ids' => 'array',
            'is_active' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }
}
