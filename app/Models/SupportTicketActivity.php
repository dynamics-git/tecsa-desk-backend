<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
