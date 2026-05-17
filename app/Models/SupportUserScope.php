<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportUserScope extends Model
{
    protected $fillable = [
        'user_id',
        'user_name',
        'user_email',
        'visibility_mode',
        'team_ids',
        'queue_ids',
        'customer_ids',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'team_ids' => 'array',
            'queue_ids' => 'array',
            'customer_ids' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
