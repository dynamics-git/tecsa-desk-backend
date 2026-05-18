<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupportTicketActivity extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'support_ticket_id',
        'parent_activity_id',
        'title',
        'type',
        'message',
        'html_body',
        'author_name',
        'author_id',
        'visibility',
        'is_internal',
        'related_entity_id',
        'occurred_at',
    ];

    /**
     * @return BelongsTo<SupportTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    /**
     * @return HasMany<SupportTicketActivityMention, $this>
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(SupportTicketActivityMention::class, 'activity_id');
    }

    /**
     * @return HasMany<SupportActivityRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(SupportActivityRead::class, 'activity_id');
    }

    /**
     * @return BelongsToMany<SupportTicketAttachment, $this>
     */
    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(
            SupportTicketAttachment::class,
            'support_ticket_activity_attachments',
            'activity_id',
            'attachment_id',
        )->withTimestamps();
    }

    /**
     * @return HasOne<SupportTicketEmailMessage, $this>
     */
    public function emailMessage(): HasOne
    {
        return $this->hasOne(SupportTicketEmailMessage::class, 'activity_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
