<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportActivityRead extends Model
{
    protected $fillable = [
        'activity_id',
        'user_id',
        'read_at',
    ];

    /**
     * @return BelongsTo<SupportTicketActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(SupportTicketActivity::class, 'activity_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }
}
