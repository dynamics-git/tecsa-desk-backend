<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketActivityMention extends Model
{
    protected $fillable = [
        'activity_id',
        'mentioned_user_id',
        'mentioned_team_id',
        'display_name',
        'kind',
    ];

    /**
     * @return BelongsTo<SupportTicketActivity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(SupportTicketActivity::class, 'activity_id');
    }
}
