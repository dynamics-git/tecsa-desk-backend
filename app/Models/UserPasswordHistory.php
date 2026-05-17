<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPasswordHistory extends Model
{
    protected $fillable = [
        'user_id',
        'password_hash',
        'changed_by',
        'change_source',
    ];
}
