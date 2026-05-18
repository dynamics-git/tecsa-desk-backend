<?php

namespace App\Jobs;

use App\Models\SupportTicketEmailMessage;
use App\Models\SupportTicketAttachment;
use App\Support\Services\Conversation\SupportConversationEmailProviderInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendSupportTicketEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $emailMessageId,
    ) {}

    public function handle(SupportConversationEmailProviderInterface $provider): void
    {
        $message = SupportTicketEmailMessage::query()->find($this->emailMessageId);

        if ($message === null) {
            return;
        }

        $attachmentIds = $message->activity?->attachments?->pluck('id')->all() ?? [];

        $attachments = SupportTicketAttachment::query()
            ->whereIn('id', $attachmentIds)
            ->get(['id', 'file_name', 'disk', 'path'])
            ->map(fn (SupportTicketAttachment $attachment): array => [
                'id' => $attachment->id,
                'fileName' => $attachment->file_name,
                'disk' => (string) ($attachment->disk ?? config('filesystems.default', 'local')),
                'path' => (string) ($attachment->path ?? ''),
            ])
            ->all();

        $result = $provider->send($message->loadMissing('activity.attachments'), $attachments);

        $message->forceFill([
            'provider_message_id' => $result['providerMessageId'] ?? $message->provider_message_id,
            'delivery_status' => $result['deliveryStatus'] ?? 'sent',
            'delivered_at' => $result['deliveredAt'] ?? now('UTC')->toDateTimeString(),
            'failed_reason' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        SupportTicketEmailMessage::query()
            ->whereKey($this->emailMessageId)
            ->update([
                'delivery_status' => 'failed',
                'failed_reason' => $exception->getMessage(),
            ]);
    }
}
