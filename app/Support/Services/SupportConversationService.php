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
use App\Models\SupportTicketActivityMention;
use App\Models\SupportTicketNotificationDispatch;
use App\Models\SupportPermissionRole;
use App\Models\SupportUserScope;
use App\Models\User;
use App\Models\CustomerUserAccess;
use App\Support\Auth\CurrentUser;
use App\Support\Auth\SupportAccessResolver;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use ZipArchive;

class SupportConversationService
{
    public function __construct(
        private readonly SupportAccessResolver $supportAccessResolver,
    ) {}

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
            $textBody = $payload['textBody'] ?? null;
            $htmlBody = $payload['htmlBody'] ?? null;
            $activityMessage = is_string($textBody) && trim($textBody) !== ''
                ? $textBody
                : (is_string($htmlBody) && trim($htmlBody) !== '' ? strip_tags($htmlBody) : $payload['subject']);

            SupportTicketActivity::query()->create([
                'id' => $activityId,
                'support_ticket_id' => $ticket->id,
                'parent_activity_id' => $payload['parentActivityId'] ?? null,
                'title' => 'Email queued to recipients',
                'type' => 'email-send',
                'message' => $activityMessage,
                'html_body' => $htmlBody,
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
                'html_body' => $htmlBody,
                'text_body' => $textBody,
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
            ->with(['mentions', 'attachments', 'emailMessage', 'author:id,email'])
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
                ticket: $ticket,
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
        $ticket = SupportTicket::query()->whereKey($ticketId)->first();

        if ($ticket === null) {
            return null;
        }

        $eventTypes = array_values(array_unique($payload['eventTypes'] ?? []));
        $channels = $payload['channels'] ?? [];
        $activityId = $payload['activityId'] ?? null;

        if (! is_string($activityId) || $activityId === '') {
            return null;
        }

        $activity = SupportTicketActivity::query()
            ->with(['author:id,email', 'mentions'])
            ->where('support_ticket_id', $ticketId)
            ->whereKey($activityId)
            ->first();

        if ($activity === null) {
            return null;
        }

        $jobIds = [];
        $recipientMatrix = [];

        foreach ($eventTypes as $eventType) {
            $jobUuid = (string) Str::uuid();
            $recipientEmails = $this->resolveRecipientEmails($ticket, $activity, (string) $eventType);

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
            $recipientMatrix[$eventType] = $recipientEmails;
        }

        return [
            'queuedJobIds' => $jobIds,
            'activityId' => $activityId,
            'recipients' => $recipientMatrix,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function resolveRecipientEmails(SupportTicket $ticket, SupportTicketActivity $activity, string $eventType): array
    {
        $internalOnly = $eventType === 'internal_mention' || (bool) $activity->is_internal;

        $requesterEmail = $this->resolveRequesterEmail($ticket);
        $assigneeEmail = $this->resolveAssigneeEmail($ticket);
        $teamParticipantEmails = $this->resolveTeamParticipantEmails($ticket);
        $activityParticipantEmails = $this->resolveActivityParticipantEmails($ticket, $activity);
        $senderEmail = $this->resolveSenderEmail($activity);

        $emails = [
            ...$teamParticipantEmails,
            ...$activityParticipantEmails,
            $assigneeEmail,
        ];

        if (! $internalOnly) {
            $emails[] = $requesterEmail;
        }

        return $this->normalizeEmailList($emails, $senderEmail);
    }

    private function resolveSenderEmail(SupportTicketActivity $activity): ?string
    {
        $authorEmail = $activity->author?->email;

        if (is_string($authorEmail) && $authorEmail !== '') {
            return mb_strtolower(trim($authorEmail));
        }

        if (is_string($activity->author_id) && $activity->author_id !== '') {
            $resolvedById = User::query()->whereKey($activity->author_id)->value('email');
            if (is_string($resolvedById) && $resolvedById !== '') {
                return mb_strtolower(trim($resolvedById));
            }
        }

        return $this->resolveUserIdentifierToEmail($activity->author_name);
    }

    private function resolveRequesterEmail(SupportTicket $ticket): ?string
    {
        $fromUser = User::query()->where('name', $ticket->requester)->value('email');
        if (is_string($fromUser) && $fromUser !== '') {
            return mb_strtolower(trim($fromUser));
        }

        $fromAccess = CustomerUserAccess::query()
            ->where('is_active', true)
            ->where('user_name', $ticket->requester)
            ->where(function ($query) use ($ticket): void {
                $query->where('customer_name', $ticket->customer)
                    ->orWhere('customer_id', $ticket->customer);
            })
            ->value('user_email');

        return is_string($fromAccess) && $fromAccess !== '' ? mb_strtolower(trim($fromAccess)) : null;
    }

    private function resolveAssigneeEmail(SupportTicket $ticket): ?string
    {
        $fromUser = User::query()->where('name', $ticket->agent)->value('email');
        if (is_string($fromUser) && $fromUser !== '') {
            return mb_strtolower(trim($fromUser));
        }

        $fromScope = SupportUserScope::query()
            ->where('is_active', true)
            ->where('user_name', $ticket->agent)
            ->value('user_email');

        return is_string($fromScope) && $fromScope !== '' ? mb_strtolower(trim($fromScope)) : null;
    }

    /**
     * @return array<int, string>
     */
    private function resolveTeamParticipantEmails(SupportTicket $ticket): array
    {
        $teamKeys = $this->teamMatchKeys($ticket->team);

        $scopeEmails = SupportUserScope::query()
            ->where('is_active', true)
            ->whereNotNull('user_email')
            ->get(['user_email', 'team_ids'])
            ->filter(function (SupportUserScope $scope) use ($teamKeys): bool {
                $scopeTeams = collect($scope->team_ids ?? [])
                    ->map(fn ($team): string => mb_strtolower(trim((string) $team)))
                    ->filter(fn (string $team): bool => $team !== '')
                    ->all();

                return array_intersect($scopeTeams, $teamKeys) !== [];
            })
            ->pluck('user_email')
            ->all();

        $roleEmails = SupportPermissionRole::query()
            ->where('is_active', true)
            ->whereNotNull('user_email')
            ->get(['user_email', 'team_ids'])
            ->filter(function (SupportPermissionRole $role) use ($teamKeys): bool {
                $roleTeams = collect($role->team_ids ?? [])
                    ->map(fn ($team): string => mb_strtolower(trim((string) $team)))
                    ->filter(fn (string $team): bool => $team !== '')
                    ->all();

                return array_intersect($roleTeams, $teamKeys) !== [];
            })
            ->pluck('user_email')
            ->all();

        return $this->normalizeEmailList([...$scopeEmails, ...$roleEmails]);
    }

    /**
     * @return array<int, string>
     */
    private function resolveActivityParticipantEmails(SupportTicket $ticket, SupportTicketActivity $activity): array
    {
        $authorEmails = SupportTicketActivity::query()
            ->with('author:id,email')
            ->where('support_ticket_id', $ticket->id)
            ->get()
            ->map(fn (SupportTicketActivity $item): ?string => $item->author?->email)
            ->all();

        $mentionEmails = $activity->mentions
            ->map(fn (SupportTicketActivityMention $mention): ?string => $this->resolveUserIdentifierToEmail($mention->mentioned_user_id))
            ->all();

        return $this->normalizeEmailList([...$authorEmails, ...$mentionEmails]);
    }

    private function resolveUserIdentifierToEmail(?string $identifier): ?string
    {
        $id = trim((string) $identifier);
        if ($id === '') {
            return null;
        }

        if (filter_var($id, FILTER_VALIDATE_EMAIL)) {
            return mb_strtolower($id);
        }

        $fromUser = User::query()
            ->where('id', $id)
            ->orWhere('email', $id)
            ->orWhere('name', $id)
            ->value('email');

        if (is_string($fromUser) && $fromUser !== '') {
            return mb_strtolower(trim($fromUser));
        }

        foreach ([SupportUserScope::class, SupportPermissionRole::class, CustomerUserAccess::class] as $modelClass) {
            $email = $modelClass::query()
                ->where('user_id', $id)
                ->orWhere('user_email', $id)
                ->orWhere('user_name', $id)
                ->value('user_email');

            if (is_string($email) && $email !== '') {
                return mb_strtolower(trim($email));
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $emails
     * @return array<int, string>
     */
    private function normalizeEmailList(array $emails, ?string $exclude = null): array
    {
        $normalizedExclude = $exclude !== null ? mb_strtolower(trim($exclude)) : null;

        return collect($emails)
            ->map(fn ($email): string => mb_strtolower(trim((string) $email)))
            ->filter(fn (string $email): bool => $this->isEmailLikeIdentifier($email))
            ->reject(fn (string $email): bool => $normalizedExclude !== null && $email === $normalizedExclude)
            ->unique()
            ->values()
            ->all();
    }

    private function isEmailLikeIdentifier(string $email): bool
    {
        if ($email === '' || str_contains($email, ' ')) {
            return false;
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            return true;
        }

        if (! str_contains($email, '@')) {
            return false;
        }

        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return $local !== '' && $domain !== '';
    }

    /**
     * @return array<int, string>
     */
    private function teamMatchKeys(?string $team): array
    {
        $raw = mb_strtolower(trim((string) $team));
        if ($raw === '') {
            return [];
        }

        $slug = str_replace(' ', '-', $raw);
        $compact = str_replace(' ', '', $raw);

        return array_values(array_unique([$raw, $slug, $compact]));
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
    private function mapActivity(SupportTicket $ticket, SupportTicketActivity $activity, CurrentUser $currentUser, ?SupportActivityRead $readState = null): array
    {
        $activityBody = $activity->type === 'email-send'
            ? ($activity->emailMessage?->text_body ?? $activity->message)
            : $activity->message;
        $activityHtmlBody = $activity->type === 'email-send'
            ? ($activity->emailMessage?->html_body ?? $activity->html_body)
            : $activity->html_body;

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
            'body' => $activityBody,
            'htmlBody' => $activityHtmlBody,
            'authorId' => $activity->author_id,
            'authorName' => $activity->author_name,
            'authorEmail' => $activity->author?->email,
            'senderType' => $this->senderTypeForActivity($ticket, $activity),
            'visibility' => $activity->visibility,
            'isInternal' => (bool) $activity->is_internal,
            'parentActivityId' => $activity->parent_activity_id,
            'createdAt' => $activity->occurred_at?->toIso8601ZuluString(),
            'attachments' => $activity->attachments->map(fn (SupportTicketAttachment $attachment): array => $this->mapAttachment($attachment))->all(),
            'mentions' => $mentions,
            'recipients' => [
                'to' => $activity->emailMessage?->to_recipients ?? [],
                'cc' => $activity->emailMessage?->cc_recipients ?? [],
                'bcc' => $activity->emailMessage?->bcc_recipients ?? [],
            ],
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

    private function senderTypeForActivity(SupportTicket $ticket, SupportTicketActivity $activity): ?string
    {
        $authorId = trim((string) ($activity->author_id ?? ''));
        $authorName = trim((string) ($activity->author_name ?? ''));

        if ($authorId === '' && $authorName === '') {
            return 'System';
        }

        if ($authorId !== '') {
            $context = $this->supportAccessResolver->authPayload(new CurrentUser(
                id: $authorId,
                name: $authorName !== '' ? $authorName : 'Unknown',
                email: $activity->author?->email,
            ));

            if ((bool) ($context['isAdmin'] ?? false)) {
                return 'Admin';
            }

            if (($context['userType'] ?? 'Internal') === 'Customer') {
                return 'Requester';
            }

            return 'Agent';
        }

        if ($authorName !== '' && strcasecmp($authorName, (string) $ticket->requester) === 0) {
            return 'Requester';
        }

        if ($authorName !== '' && strcasecmp($authorName, (string) $ticket->agent) === 0) {
            return 'Agent';
        }

        return null;
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
