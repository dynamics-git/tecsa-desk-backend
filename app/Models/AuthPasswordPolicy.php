<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthPasswordPolicy extends Model
{
    protected $fillable = [
        'min_length',
        'require_uppercase',
        'require_lowercase',
        'require_number',
        'require_symbol',
        'disallow_common_passwords',
        'history_count',
        'max_age_days',
        'lockout_threshold',
        'lockout_duration_minutes',
        'allow_password_generate',
        'allow_manual_password_set',
        'force_change_on_first_login_default',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_length' => 'integer',
            'require_uppercase' => 'boolean',
            'require_lowercase' => 'boolean',
            'require_number' => 'boolean',
            'require_symbol' => 'boolean',
            'disallow_common_passwords' => 'boolean',
            'history_count' => 'integer',
            'max_age_days' => 'integer',
            'lockout_threshold' => 'integer',
            'lockout_duration_minutes' => 'integer',
            'allow_password_generate' => 'boolean',
            'allow_manual_password_set' => 'boolean',
            'force_change_on_first_login_default' => 'boolean',
            'updated_by' => 'integer',
        ];
    }
}
