<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SupportTicketAttachment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'support_ticket_id',
        'file_name',
        'disk',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
        'customer',
        'requester',
        'visibility',
        'uploaded_at',
    ];

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * @return BelongsToMany<SupportTicketActivity, $this>
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(
            SupportTicketActivity::class,
            'support_ticket_activity_attachments',
            'attachment_id',
            'activity_id',
        )->withTimestamps();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }
}
