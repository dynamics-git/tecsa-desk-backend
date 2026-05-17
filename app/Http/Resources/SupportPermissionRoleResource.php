<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportPermissionRoleResource extends JsonResource
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
            'name' => $this->role,
            'role' => $this->role,
            'userId' => $this->user_id !== null ? (string) $this->user_id : null,
            'userEmail' => $this->user_email,
            'userType' => $this->user_type,
            'ticketVisibility' => $this->ticket_visibility,
            'permissions' => $this->permissions ?? [],
            'userIds' => $this->user_ids ?? [],
            'teamIds' => $this->team_ids ?? [],
            'customerIds' => $this->customer_ids ?? [],
            'isActive' => (bool) ($this->is_active ?? true),
            'isAdmin' => (bool) ($this->is_admin ?? false),
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
