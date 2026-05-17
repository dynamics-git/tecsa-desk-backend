<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportUserScopeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'userId' => $this->user_id !== null ? (string) $this->user_id : null,
            'userName' => $this->user_name,
            'userEmail' => $this->user_email,
            'visibilityMode' => $this->visibility_mode,
            'teamIds' => $this->team_ids ?? [],
            'queueIds' => $this->queue_ids ?? [],
            'customerIds' => $this->customer_ids ?? [],
            'isActive' => (bool) ($this->is_active ?? true),
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
