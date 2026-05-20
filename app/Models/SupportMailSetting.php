<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMailSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'mailer',
        'host',
        'port',
        'encryption',
        'username',
        'password',
        'from_address',
        'from_name',
        'reply_to_address',
        'timeout',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'timeout' => 'integer',
            'is_active' => 'boolean'
        ];
    }
}
