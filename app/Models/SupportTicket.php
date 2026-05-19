<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'subject',
        'submeta',
        'customer',
        'priority',
        'status',
        'agent',
        'requester',
        'team',
        'source',
        'created_by_type',
        'category',
        'is_assigned_to_me',
        'is_waiting_on_customer',
        'sla_first_response_at',
        'sla_resolution_at',
    ];

    /**
     * @return HasMany<SupportTicketActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(SupportTicketActivity::class)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<SupportTicketRelatedItem, $this>
     */
    public function relatedItems(): HasMany
    {
        return $this->hasMany(SupportTicketRelatedItem::class);
    }

    /**
     * @return HasMany<SupportTicketLinkedTask, $this>
     */
    public function linkedTasks(): HasMany
    {
        return $this->hasMany(SupportTicketLinkedTask::class);
    }

    /**
     * @return HasMany<SupportTicketAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportTicketAttachment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_assigned_to_me' => 'boolean',
            'is_waiting_on_customer' => 'boolean',
            'sla_first_response_at' => 'datetime',
            'sla_resolution_at' => 'datetime',
        ];
    }
}
