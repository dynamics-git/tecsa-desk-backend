<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupportMacro extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'title', 'body', 'visibility', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
