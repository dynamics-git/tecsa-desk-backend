<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketNotificationDispatch extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'activity_id',
        'event_type',
        'channels',
        'job_uuid',
        'status',
        'queued_at',
        'processed_at',
        'failed_reason',
    ];

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

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
            'channels' => 'array',
            'queued_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
