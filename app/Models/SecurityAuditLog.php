<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SecurityAuditLog extends Model
{
    protected $fillable = [
        'actor_id',
        'target_user_id',
        'action',
        'source_ip',
        'user_agent',
        'reason',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_id' => 'integer',
            'target_user_id' => 'integer',
            'metadata' => 'array',
        ];
    }
}
