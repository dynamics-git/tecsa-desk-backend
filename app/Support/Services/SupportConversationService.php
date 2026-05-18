<?php

namespace App\Support\Services;

use App\Jobs\DispatchSupportTicketNotificationJob;
use App\Jobs\SendSupportTicketEmailJob;
use App\Models\SupportActivityRead;
use App\Models\SupportAttachmentBundleExport;
use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketEmailMessage;
use App\Models\SupportTicketNotificationDispatch;
use App\Support\Auth\CurrentUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use ZipArchive;

class SupportConversationService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function sendEmail(string $ticketId, array $payload, CurrentUser $currentUser): ?array
    {
        $ticket = SupportTicket::query()->find($ticketId);

        if ($ticket === null) {
            return null;
        }

        return DB::transaction(function () use ($ticket, $payload, $currentUser): array {
            $now = Carbon::now('UTC');
            $activityId = $this->nextActivityId();
            $providerMessageId = 'queued-'.Str::uuid()->toString();

            SupportTicketActivity::query()->create([
                'id' => $activityId,
                'support_ticket_id' => $ticket->id,
                'parent_activity_id' => $payload['parentActivityId'] ?? null,
                'title' => 'Email queued to recipients',
                'type' => 'email-send',
                'message' => $payload['subject'],
                'author_name' => $currentUser->name,
                'author_id' => $currentUser->id,
                'visibility' => 'public',
                'is_internal' => false,
                'occurred_at' => $now,
            ]);

            $attachmentIds = $this->normalizeAttachmentIds($payload['attachmentIds'] ?? []);
            $this->attachAttachmentsToActivity($activityId, $ticket->id, $attachmentIds);

            $email = SupportTicketEmailMessage::query()->create([
                'support_ticket_id' => $ticket->id,
                'activity_id' => $activityId,
                'provider_message_id' => $providerMessageId,
                'delivery_status' => 'queued',
                'to_recipients' => array_values(array_unique($payload['to'] ?? [])),
                'cc_recipients' => array_values(array_unique($payload['cc'] ?? [])),
                'bcc_recipients' => array_values(array_unique($payload['bcc'] ?? [])),
                'subject' => $payload['subject'],
                'html_body' => $payload['htmlBody'] ?? null,
                'text_body' => $payload['textBody'] ?? null,
                'queued_at' => $now,
            ]);

            SendSupportTicketEmailJob::dispatch((int) $email->id);

            return [
                'activityId' => $activityId,
                'providerMessageId' => $providerMessageId,
                'deliveryStatus' => 'queued',
                'queuedAt' => $now->toIso8601ZuluString(),
            ];
        });
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function activities(string $ticketId, CurrentUser $currentUser): ?array
    {
        $ticket = SupportTicket::query()->find($ticketId);

        if ($ticket === null) {
            return null;
        }

        $activities = SupportTicketActivity::query()
            ->with(['mentions', 'attachments', 'emailMessage'])
            ->where('support_ticket_id', $ticketId)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->get();

        $readStates = SupportActivityRead::query()
            ->where('user_id', $currentUser->id)
            ->whereIn('activity_id', $activities->pluck('id')->all())
            ->get()
            ->keyBy('activity_id');

        return $activities
            ->map(fn (SupportTicketActivity $activity): array => $this->mapActivity(
                activity: $activity,
                currentUser: $currentUser,
                readState: $readStates->get($activity->id),
            ))
            ->all();
    }

    /**
     * @param  array<int, string>  $activityIds
     */
    public function markActivitiesRead(string $ticketId, array $activityIds, CurrentUser $currentUser): int
    {
        $normalizedIds = $this->normalizeActivityIds($activityIds);

        if ($normalizedIds === []) {
            return 0;
        }

        $matchingIds = SupportTicketActivity::query()
            ->where('support_ticket_id', $ticketId)
            ->whereIn('id', $normalizedIds)
            ->pluck('id')
            ->all();

        if ($matchingIds === []) {
            return 0;
        }

        $now = Carbon::now('UTC');

        $rows = collect($matchingIds)
            ->map(fn (string $activityId): array => [
                'activity_id' => $activityId,
                'user_id' => $currentUser->id,
                'read_at' => $now->toDateTimeString(),
                'created_at' => $now->toDateTimeString(),
                'updated_at' => $now->toDateTimeString(),
            ])
            ->all();

        DB::table('support_activity_reads')->upsert(
            $rows,
            ['activity_id', 'user_id'],
            ['read_at', 'updated_at'],
        );

        return count($matchingIds);
    }

    public function markAllActivitiesRead(string $ticketId, CurrentUser $currentUser): int
    {
        $activityIds = SupportTicketActivity::query()
            ->where('support_ticket_id', $ticketId)
            ->pluck('id')
            ->all();

        if ($activityIds === []) {
            return 0;
        }

        return $this->markActivitiesRead($ticketId, $activityIds, $currentUser);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function ticketAttachments(string $ticketId, array $query): ?array
    {
        if (! SupportTicket::query()->whereKey($ticketId)->exists()) {
            return null;
        }

        $page = (int) ($query['page'] ?? 1);
        $pageSize = (int) ($query['pageSize'] ?? 20);

        $attachments = SupportTicketAttachment::query()
            ->with('ticket')
            ->where('support_ticket_id', $ticketId)
            ->when($query['visibility'] ?? null, fn ($builder, string $visibility) => $builder->where('visibility', $visibility))
            ->when($query['search'] ?? null, function ($builder, string $search): void {
                $builder->where(function ($builder) use ($search): void {
                    $builder
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('uploaded_by', 'like', "%{$search}%");
                });
            });

        $total = (clone $attachments)->count();

        $items = $attachments
            ->latest('uploaded_at')
            ->offset(($page - 1) * $pageSize)
            ->limit($pageSize)
            ->get()
            ->map(fn (SupportTicketAttachment $attachment): array => $this->mapAttachment($attachment))
            ->all();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{downloadUrl: string}|null
     */
    public function downloadAllAttachmentBundle(string $ticketId, array $payload, CurrentUser $currentUser): ?array
    {
        if (! SupportTicket::query()->whereKey($ticketId)->exists()) {
            return null;
        }

        $requestedIds = $this->normalizeAttachmentIds($payload['attachmentIds'] ?? []);

        $attachments = SupportTicketAttachment::query()
            ->where('support_ticket_id', $ticketId)
            ->when($requestedIds !== [], fn ($builder) => $builder->whereIn('id', $requestedIds))
            ->get(['id', 'file_name', 'disk', 'path']);

        if ($attachments->isEmpty()) {
            return null;
        }

        $bundleId = 'bundle-'.Str::uuid()->toString();
        $relativePath = 'support-attachment-bundles/'.$bundleId.'.zip';
        $absolutePath = storage_path('app/'.$relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0777, true);
        }

        $zip = new ZipArchive();
        $opened = $zip->open($absolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            return null;
        }

        foreach ($attachments as $attachment) {
            $disk = (string) ($attachment->disk ?? config('filesystems.default', 'local'));
            $path = (string) ($attachment->path ?? '');

            if ($path === '' || ! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $zip->addFile(Storage::disk($disk)->path($path), (string) $attachment->file_name);
        }

        $zip->close();

        $expiresAt = Carbon::now('UTC')->addMinutes(30);

        SupportAttachmentBundleExport::query()->create([
            'id' => $bundleId,
            'support_ticket_id' => $ticketId,
            'disk' => 'local',
            'path' => $relativePath,
            'expires_at' => $expiresAt,
            'created_by' => $currentUser->name,
        ]);

        return [
            'downloadUrl' => URL::temporarySignedRoute('support.attachments.bundle-download', $expiresAt, ['bundleId' => $bundleId]),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{queuedJobIds: array<int, string>}|null
     */
    public function dispatchNotifications(string $ticketId, array $payload): ?array
    {
        if (! SupportTicket::query()->whereKey($ticketId)->exists()) {
            return null;
        }

        $eventTypes = array_values(array_unique($payload['eventTypes'] ?? []));
        $channels = $payload['channels'] ?? [];
        $activityId = $payload['activityId'] ?? null;
        $jobIds = [];

        foreach ($eventTypes as $eventType) {
            $jobUuid = (string) Str::uuid();

            $dispatch = SupportTicketNotificationDispatch::query()->create([
                'support_ticket_id' => $ticketId,
                'activity_id' => $activityId,
                'event_type' => $eventType,
                'channels' => $channels,
                'job_uuid' => $jobUuid,
                'status' => 'queued',
                'queued_at' => Carbon::now('UTC'),
            ]);

            DispatchSupportTicketNotificationJob::dispatch((int) $dispatch->id);
            $jobIds[] = $jobUuid;
        }

        return ['queuedJobIds' => $jobIds];
    }

    /**
     * @param  array<int, string>  $attachmentIds
     */
    private function attachAttachmentsToActivity(string $activityId, string $ticketId, array $attachmentIds): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $existingIds = SupportTicketAttachment::query()
            ->where('support_ticket_id', $ticketId)
            ->whereIn('id', $attachmentIds)
            ->pluck('id')
            ->all();

        if ($existingIds === []) {
            return;
        }

        $rows = collect($existingIds)
            ->map(fn (string $attachmentId): array => [
                'activity_id' => $activityId,
                'attachment_id' => $attachmentId,
                'created_at' => Carbon::now('UTC'),
                'updated_at' => Carbon::now('UTC'),
            ])
            ->all();

        DB::table('support_ticket_activity_attachments')->upsert(
            $rows,
            ['activity_id', 'attachment_id'],
            ['updated_at'],
        );
    }

    /**
     * @param  array<int, mixed>  $attachmentIds
     * @return array<int, string>
     */
    private function normalizeAttachmentIds(array $attachmentIds): array
    {
        return collect($attachmentIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function nextActivityId(): string
    {
        $latestNumber = SupportTicketActivity::query()
            ->where('id', 'like', 'ACT-%')
            ->pluck('id')
            ->map(fn (string $id): int => (int) Str::after($id, 'ACT-'))
            ->max();

        $next = $latestNumber === null || $latestNumber < 10000 ? 10001 : $latestNumber + 1;

        return 'ACT-'.$next;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapActivity(SupportTicketActivity $activity, CurrentUser $currentUser, ?SupportActivityRead $readState = null): array
    {
        $mentions = $activity->mentions->map(fn ($mention): array => [
            'id' => $mention->mentioned_user_id ?? $mention->mentioned_team_id,
            'display' => $mention->display_name,
            'kind' => $mention->kind,
        ])->all();

        $mentionedNames = collect($mentions)
            ->pluck('display')
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();

        return [
            'id' => $activity->id,
            'title' => $activity->title,
            'type' => $activity->type,
            'body' => $activity->message,
            'htmlBody' => $activity->html_body,
            'authorId' => $activity->author_id,
            'authorName' => $activity->author_name,
            'visibility' => $activity->visibility,
            'isInternal' => (bool) $activity->is_internal,
            'parentActivityId' => $activity->parent_activity_id,
            'createdAt' => $activity->occurred_at?->toIso8601ZuluString(),
            'attachments' => $activity->attachments->map(fn (SupportTicketAttachment $attachment): array => $this->mapAttachment($attachment))->all(),
            'mentions' => $mentions,
            'providerMessageId' => $activity->emailMessage?->provider_message_id,
            'deliveryStatus' => $activity->emailMessage?->delivery_status,
            'deliveredAt' => $activity->emailMessage?->delivered_at?->toIso8601ZuluString(),
            'failedReason' => $activity->emailMessage?->failed_reason,
            'isUnread' => $readState === null,
            'readAt' => $readState?->read_at?->toIso8601ZuluString(),
            'mentionedCurrentUser' => $this->mentionedCurrentUser($mentions, $currentUser),
            'mentionedNames' => $mentionedNames,
        ];
    }

    /**
     * @param  array<int, mixed>  $activityIds
     * @return array<int, string>
     */
    private function normalizeActivityIds(array $activityIds): array
    {
        return collect($activityIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{id: string|null, display: string, kind: string}>  $mentions
     */
    private function mentionedCurrentUser(array $mentions, CurrentUser $currentUser): bool
    {
        $userId = strtolower(trim($currentUser->id));
        $userEmail = strtolower(trim((string) ($currentUser->email ?? '')));
        $userName = strtolower(trim($currentUser->name));

        foreach ($mentions as $mention) {
            if (($mention['kind'] ?? null) !== 'user') {
                continue;
            }

            $mentionId = strtolower(trim((string) ($mention['id'] ?? '')));
            $display = strtolower(trim((string) ($mention['display'] ?? '')));

            if ($mentionId !== '' && ($mentionId === $userId || ($userEmail !== '' && $mentionId === $userEmail))) {
                return true;
            }

            if ($display !== '' && ($display === $userName || ($userEmail !== '' && $display === $userEmail))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAttachment(SupportTicketAttachment $attachment): array
    {
        $expiresAt = Carbon::now('UTC')->addMinutes(30);
        $activityId = $attachment->activities()->latest('support_ticket_activity_attachments.created_at')->value('support_ticket_activities.id');

        return [
            'id' => $attachment->id,
            'fileName' => $attachment->file_name,
            'size' => (int) $attachment->size,
            'mimeType' => $attachment->mime_type,
            'uploadedBy' => $attachment->uploaded_by,
            'uploadedAt' => $attachment->uploaded_at?->toIso8601ZuluString(),
            'visibility' => $attachment->visibility,
            'ticketId' => $attachment->support_ticket_id,
            'ticketSubject' => $attachment->ticket?->subject,
            'activityId' => $activityId,
            'previewUrl' => URL::temporarySignedRoute('support.attachments.preview', $expiresAt, ['id' => $attachment->id]),
            'downloadUrl' => URL::temporarySignedRoute('support.attachments.download', $expiresAt, ['id' => $attachment->id]),
        ];
    }
}
