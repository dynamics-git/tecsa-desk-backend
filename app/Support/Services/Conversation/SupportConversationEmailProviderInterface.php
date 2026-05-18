<?php

namespace App\Support\Services\Conversation;

use App\Models\SupportTicketEmailMessage;

interface SupportConversationEmailProviderInterface
{
    /**
     * @param  array<int, array{id: string, fileName: string, disk: string, path: string}>  $attachments
     * @return array{providerMessageId: string, deliveryStatus: string, deliveredAt: string|null}
     */
    public function send(SupportTicketEmailMessage $message, array $attachments): array;
}
