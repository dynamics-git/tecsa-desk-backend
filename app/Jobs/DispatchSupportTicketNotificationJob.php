<?php

namespace App\Jobs;

use App\Models\SupportTicketNotificationDispatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DispatchSupportTicketNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /**
     * @var array<int, int>
     */
    public array $backoff = [15, 60, 180];

    public function __construct(
        public readonly int $dispatchId,
    ) {}

    public function handle(): void
    {
        $dispatch = SupportTicketNotificationDispatch::query()->find($this->dispatchId);

        if ($dispatch === null) {
            return;
        }

        $dispatch->forceFill([
            'status' => 'sent',
            'processed_at' => now('UTC'),
            'failed_reason' => null,
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        SupportTicketNotificationDispatch::query()
            ->whereKey($this->dispatchId)
            ->update([
                'status' => 'failed',
                'failed_reason' => $exception->getMessage(),
            ]);
    }
}
