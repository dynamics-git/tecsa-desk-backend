<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportQueue extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'name', 'team_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
