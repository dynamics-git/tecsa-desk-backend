<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerUserAccessResource extends JsonResource
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
            'customerId' => $this->customer_id,
            'customerName' => $this->customer_name,
            'accessLevel' => $this->access_level,
            'canCreateTicket' => (bool) ($this->can_create_ticket ?? false),
            'canViewAttachments' => (bool) ($this->can_view_attachments ?? false),
            'canReply' => (bool) ($this->can_reply ?? false),
            'isActive' => (bool) ($this->is_active ?? true),
            'createdAt' => $this->created_at?->toIso8601ZuluString(),
            'updatedAt' => $this->updated_at?->toIso8601ZuluString(),
        ];
    }
}
