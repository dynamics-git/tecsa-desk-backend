<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketEmailMessage extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'activity_id',
        'provider_message_id',
        'delivery_status',
        'to_recipients',
        'cc_recipients',
        'bcc_recipients',
        'subject',
        'html_body',
        'text_body',
        'queued_at',
        'delivered_at',
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
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'bcc_recipients' => 'array',
            'queued_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
