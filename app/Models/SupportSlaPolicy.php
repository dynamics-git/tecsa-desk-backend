<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportSlaPolicy extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'priority', 'first_response_minutes', 'resolution_minutes', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
