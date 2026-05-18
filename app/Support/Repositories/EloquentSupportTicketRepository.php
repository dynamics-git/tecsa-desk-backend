<?php

namespace App\Support\Repositories;

use App\Models\SupportTicket;
use App\Models\SupportTicketActivity;
use App\Models\SupportTicketActivityMention;
use App\Models\SupportTicketAttachment;
use App\Models\SupportTicketLinkedTask;
use App\Support\Auth\CurrentUser;
use App\Support\Auth\SupportAccessResolver;
use App\Support\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentSupportTicketRepository implements SupportTicketRepositoryInterface
{
    public function __construct(
        private readonly SupportAccessResolver $supportAccessResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, ?string $sort, ?CurrentUser $currentUser = null): array
    {
        $actor = $this->actor($currentUser);
        $query = SupportTicket::query();
        $query->withCount(['linkedTasks', 'attachments']);
        $query->with(['activities' => fn ($query) => $query->latest('occurred_at')]);

        $this->supportAccessResolver->applyTicketScope($query, $actor);
        $this->applyFilters($query, $filters);
        $this->applySort($query, $sort);

        return $query->get()->map(fn (SupportTicket $ticket): array => $this->mapTicket($ticket))->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id, ?CurrentUser $currentUser = null): ?array
    {
        $actor = $this->actor($currentUser);
        $query = SupportTicket::query()
            ->with(['activities.mentions', 'relatedItems'])
            ->with(['linkedTasks' => fn ($query) => $query->latest(), 'attachments' => fn ($query) => $query->latest('uploaded_at')])
            ->where('id', $id);
        $this->supportAccessResolver->applyTicketScope($query, $actor);
        $ticket = $query->first();

        return $ticket === null ? null : $this->mapTicket($ticket, includeDetail: true);
    }

    public function create(array $payload, ?CurrentUser $currentUser = null): array
    {
        return DB::transaction(function () use ($payload, $currentUser): array {
            $actor = $this->actor($currentUser);
            $ticketId = $this->nextTicketId();
            $now = Carbon::now('UTC');

            $ticket = SupportTicket::query()->create([
                'id' => $ticketId,
                'subject' => $payload['subject'],
                'submeta' => $payload['category'],
                'customer' => $payload['customer'],
                'priority' => $payload['priority'],
                'status' => 'Open',
                'agent' => $actor->name,
                'requester' => $payload['requester'],
                'team' => $payload['team'],
                'source' => 'Customer Portal',
                'category' => $payload['category'],
                'is_assigned_to_me' => true,
                'is_waiting_on_customer' => false,
                'sla_first_response_at' => $now->copy()->addHour(),
                'sla_resolution_at' => $now->copy()->addHours(8),
            ]);

            if (! empty($payload['message'])) {
                $createdActivityId = $this->createActivity(
                    ticketId: $ticket->id,
                    title: 'Ticket created',
                    type: 'ticket-created',
                    message: $payload['message'],
                    authorId: $actor->id,
                    authorName: $actor->name,
                    visibility: 'public',
                );

                $this->attachActivityAttachments($createdActivityId, $ticket->id, $payload['attachmentIds'] ?? []);
            }

            $attachmentIds = $payload['attachmentIds'] ?? [];
            $this->attachReferences($ticket->id, $attachmentIds, $actor->name, 'public', $actor);

            return ['success' => true, 'ticketId' => $ticket->id];
        });
    }

    public function linkedTasks(string $ticketId): ?array
    {
        if (! SupportTicket::query()->whereKey($ticketId)->exists()) {
            return null;
        }

        return SupportTicketLinkedTask::query()
            ->where('support_ticket_id', $ticketId)
            ->latest()
            ->get()
            ->map(fn (SupportTicketLinkedTask $task): array => $this->mapLinkedTask($task))
            ->all();
    }

    public function attachments(array $query): array
    {
        return $this->paginatedAttachments($query);
    }

    public function ticketAttachments(string $ticketId, array $query): ?array
    {
        if (! SupportTicket::query()->whereKey($ticketId)->exists()) {
            return null;
        }

        return $this->paginatedAttachments([...$query, 'ticketId' => $ticketId]);
    }

    public function uploadAttachment(UploadedFile $file, array $payload, ?CurrentUser $currentUser = null): array
    {
        return DB::transaction(function () use ($file, $payload, $currentUser): array {
            $actor = $this->actor($currentUser);
            $attachmentId = $this->nextAttachmentId();
            $ticketId = $payload['ticketId'] ?? null;
            $uploadedBy = $payload['requester'] ?? $actor->name;
            $path = $file->storeAs(
                'support-attachments',
                $attachmentId.'-'.$this->safeFileName($file->getClientOriginalName()),
            );

            $attachment = SupportTicketAttachment::query()->create([
                'id' => $attachmentId,
                'support_ticket_id' => $ticketId,
                'file_name' => $file->getClientOriginalName(),
                'disk' => config('filesystems.default', 'local'),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize() ?: 0,
                'uploaded_by' => $uploadedBy,
                'customer' => $payload['customer'] ?? null,
                'requester' => $payload['requester'] ?? null,
                'visibility' => $payload['visibility'],
                'uploaded_at' => Carbon::now('UTC'),
            ]);

            if ($ticketId !== null) {
                $this->createActivity(
                    ticketId: $ticketId,
                    title: 'Attachment added',
                    type: 'attachment-added',
                    message: "Attachment {$attachmentId} added.",
                    authorId: $actor->id,
                    authorName: $uploadedBy,
                    visibility: $payload['visibility'],
                    isInternal: $payload['visibility'] === 'internal',
                    relatedEntityId: $attachmentId,
                );
            }

            return $this->mapAttachment($attachment->load('ticket'));
        });
    }

    public function assign(array $ticketIds, string $agent, ?CurrentUser $currentUser = null): int
    {
        return DB::transaction(function () use ($ticketIds, $agent, $currentUser): int {
            $actor = $this->actor($currentUser);
            $tickets = SupportTicket::query()->whereIn('id', array_unique($ticketIds))->get();

            foreach ($tickets as $ticket) {
                $ticket->forceFill([
                    'agent' => $agent,
                    'is_assigned_to_me' => Str::lower($agent) === Str::lower($actor->name),
                    'updated_at' => Carbon::now('UTC'),
                ])->save();

                $this->createActivity($ticket->id, "{$agent} was assigned the ticket", 'assignee-change', authorId: $actor->id, authorName: $actor->name);
            }

            return $tickets->count();
        });
    }

    public function updateStatus(array $ticketIds, string $status, ?CurrentUser $currentUser = null): int
    {
        return DB::transaction(function () use ($ticketIds, $status, $currentUser): int {
            $actor = $this->actor($currentUser);
            $tickets = SupportTicket::query()->whereIn('id', array_unique($ticketIds))->get();

            foreach ($tickets as $ticket) {
                $ticket->forceFill([
                    'status' => $status,
                    'is_waiting_on_customer' => $status === TicketStatus::PendingCustomer->value,
                    'updated_at' => Carbon::now('UTC'),
                ])->save();

                $this->createActivity($ticket->id, "Status changed to {$status}", 'status-change', authorId: $actor->id, authorName: $actor->name);
            }

            return $tickets->count();
        });
    }

    public function updatePriority(array $ticketIds, string $priority, ?CurrentUser $currentUser = null): int
    {
        return DB::transaction(function () use ($ticketIds, $priority, $currentUser): int {
            $actor = $this->actor($currentUser);
            $tickets = SupportTicket::query()->whereIn('id', array_unique($ticketIds))->get();

            foreach ($tickets as $ticket) {
                $ticket->forceFill([
                    'priority' => $priority,
                    'updated_at' => Carbon::now('UTC'),
                ])->save();

                $this->createActivity($ticket->id, "Priority changed to {$priority}", 'priority-change', authorId: $actor->id, authorName: $actor->name);
            }

            return $tickets->count();
        });
    }

    public function addReply(
        string $ticketId,
        string $message,
        bool $isInternalNote,
        ?string $htmlBody = null,
        array $attachmentIds = [],
        ?string $parentActivityId = null,
        array $mentions = [],
        ?CurrentUser $currentUser = null,
    ): ?string {
        $ticket = SupportTicket::query()->find($ticketId);

        if ($ticket === null) {
            return null;
        }

        return DB::transaction(function () use ($ticket, $message, $htmlBody, $isInternalNote, $attachmentIds, $parentActivityId, $mentions, $currentUser): string {
            $actor = $this->actor($currentUser);
            $ticket->forceFill(['updated_at' => Carbon::now('UTC')])->save();
            $this->attachReferences($ticket->id, $attachmentIds, $actor->name, $isInternalNote ? 'internal' : 'public', $actor);

            $activityId = $this->createActivity(
                ticketId: $ticket->id,
                title: $isInternalNote ? 'Internal note added' : 'Reply sent to requester',
                type: $isInternalNote ? 'note' : 'reply',
                message: $message,
                htmlBody: $htmlBody,
                authorId: $actor->id,
                authorName: $actor->name,
                visibility: $isInternalNote ? 'internal' : 'public',
                isInternal: $isInternalNote,
                parentActivityId: $parentActivityId,
                mentions: $mentions,
            );

            $this->attachActivityAttachments($activityId, $ticket->id, $attachmentIds);

            return $activityId;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{forwardId: string, linkedTaskId: string|null}|null
     */
    public function forward(string $ticketId, array $payload, ?CurrentUser $currentUser = null): ?array
    {
        $ticket = SupportTicket::query()->find($ticketId);

        if ($ticket === null) {
            return null;
        }

        return DB::transaction(function () use ($ticket, $payload, $currentUser): array {
            $actor = $this->actor($currentUser);
            $ticket->forceFill(['updated_at' => Carbon::now('UTC')])->save();

            $linkedTaskId = null;
            $mode = $payload['mode'];
            $comment = $payload['comment'] ?? null;
            $attachmentIds = $payload['attachmentIds'] ?? [];
            $this->attachReferences($ticket->id, $attachmentIds, $actor->name, 'internal', $actor);

            if ($mode === 'team') {
                $teamName = $this->teamDisplayName($payload['teamId']);
                $ticket->forceFill(['team' => $teamName])->save();

                $title = 'Ticket forwarded to team';
                $type = 'forward';
                $body = "Forwarded to {$teamName}";
                $body .= $comment ? " with internal comment: {$comment}" : '.';
                $relatedEntityId = $payload['teamId'];
            } elseif ($mode === 'task') {
                $linkedTaskId = $this->createLinkedTask($ticket->id, $payload, $attachmentIds);
                $assignee = $payload['taskAssignee'] ?? 'Unassigned';

                $title = 'Linked task created';
                $type = 'linked-task-created';
                $body = "Created linked task {$linkedTaskId} assigned to {$assignee}.";
                $body .= $comment ? " Comment: {$comment}" : '';
                $relatedEntityId = $linkedTaskId;
            } else {
                $title = 'Ticket forwarded';
                $type = 'forward';
                $body = "Forwarded to {$payload['to']}";
                $body .= $comment ? " with note: {$comment}" : '.';
                $relatedEntityId = $payload['to'];
            }

            $body .= $this->attachmentSummary($attachmentIds);

            $forwardId = $this->createActivity(
                ticketId: $ticket->id,
                title: $title,
                type: $type,
                message: $body,
                authorId: $actor->id,
                authorName: $actor->name,
                visibility: 'internal',
                isInternal: true,
                relatedEntityId: $relatedEntityId,
            );

            $this->attachActivityAttachments($forwardId, $ticket->id, $attachmentIds);

            return ['forwardId' => $forwardId, 'linkedTaskId' => $linkedTaskId];
        });
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $query
            ->when($filters['queue'] ?? null, fn (Builder $query, string $queue): Builder => $query->where('team', $queue))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority): Builder => $query->where('priority', $priority))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
            ->when($filters['agent'] ?? null, fn (Builder $query, string $agent): Builder => $query->where('agent', $agent))
            ->when($filters['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('submeta', 'like', "%{$search}%")
                        ->orWhere('customer', 'like', "%{$search}%")
                        ->orWhere('requester', 'like', "%{$search}%")
                        ->orWhere('team', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            });
    }

    /**
     * @param  Builder<SupportTicket>  $query
     */
    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort ?: 'updated_desc') {
            'updated_asc' => $query->orderBy('updated_at'),
            'priority_desc' => $query->orderByRaw($this->priorityOrderSql('desc')),
            'priority_asc' => $query->orderByRaw($this->priorityOrderSql('asc')),
            'status_asc' => $query->orderBy('status'),
            'customer_asc' => $query->orderBy('customer'),
            'subject_asc' => $query->orderBy('subject'),
            default => $query->orderByDesc('updated_at'),
        };
    }

    private function priorityOrderSql(string $direction): string
    {
        return "case priority when 'Urgent' then 4 when 'High' then 3 when 'Medium' then 2 when 'Low' then 1 else 0 end {$direction}";
    }

    private function createActivity(
        string $ticketId,
        string $title,
        string $type,
        ?string $message = null,
        ?string $htmlBody = null,
        ?string $authorId = null,
        ?string $authorName = null,
        string $visibility = 'public',
        bool $isInternal = false,
        ?string $relatedEntityId = null,
        ?string $parentActivityId = null,
        array $mentions = [],
    ): string {
        $activityId = $this->nextActivityId();

        SupportTicketActivity::query()->create([
            'id' => $activityId,
            'support_ticket_id' => $ticketId,
            'parent_activity_id' => $parentActivityId,
            'title' => $title,
            'type' => $type,
            'message' => $message,
            'html_body' => $htmlBody,
            'author_name' => $authorName,
            'author_id' => $authorId,
            'visibility' => $visibility,
            'is_internal' => $isInternal,
            'related_entity_id' => $relatedEntityId,
            'occurred_at' => Carbon::now('UTC'),
        ]);

        $this->createMentions($activityId, $mentions);

        return $activityId;
    }

    /**
     * @param  array<int, array{id: string, kind: string, display: string}>  $mentions
     */
    private function createMentions(string $activityId, array $mentions): void
    {
        foreach ($mentions as $mention) {
            SupportTicketActivityMention::query()->create([
                'activity_id' => $activityId,
                'mentioned_user_id' => $mention['kind'] === 'user' ? $mention['id'] : null,
                'mentioned_team_id' => $mention['kind'] === 'team' ? $mention['id'] : null,
                'display_name' => $mention['display'],
                'kind' => $mention['kind'],
            ]);
        }
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

    private function nextTicketId(): string
    {
        $latestNumber = SupportTicket::query()
            ->where('id', 'like', 'TK-%')
            ->pluck('id')
            ->map(fn (string $id): int => (int) Str::after($id, 'TK-'))
            ->max();

        return 'TK-'.(($latestNumber ?? 1048) + 1);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $attachmentIds
     */
    private function createLinkedTask(string $ticketId, array $payload, array $attachmentIds): string
    {
        $taskId = $this->nextTaskId();

        SupportTicketLinkedTask::query()->create([
            'id' => $taskId,
            'support_ticket_id' => $ticketId,
            'title' => $payload['taskTitle'],
            'assignee' => $payload['taskAssignee'] ?? null,
            'status' => 'Open',
            'comment' => $payload['comment'] ?? null,
            'attachment_ids' => $attachmentIds,
        ]);

        return $taskId;
    }

    private function nextTaskId(): string
    {
        $latestNumber = SupportTicketLinkedTask::query()
            ->where('id', 'like', 'TASK-%')
            ->pluck('id')
            ->map(fn (string $id): int => (int) Str::after($id, 'TASK-'))
            ->max();

        $next = $latestNumber === null || $latestNumber < 2000 ? 2001 : $latestNumber + 1;

        return 'TASK-'.$next;
    }

    private function nextAttachmentId(): string
    {
        $latestNumber = SupportTicketAttachment::query()
            ->where('id', 'like', 'ATT-%')
            ->pluck('id')
            ->map(fn (string $id): int => (int) Str::after($id, 'ATT-'))
            ->max();

        $next = $latestNumber === null || $latestNumber < 2000 ? 2001 : $latestNumber + 1;

        return 'ATT-'.$next;
    }

    private function safeFileName(string $fileName): string
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $safeBaseName = Str::slug($baseName) ?: 'attachment';

        return $extension === '' ? $safeBaseName : $safeBaseName.'.'.Str::lower($extension);
    }

    private function teamDisplayName(string $teamId): string
    {
        return Str::of($teamId)->replace(['-', '_'], ' ')->headline()->toString();
    }

    /**
     * @param  array<int, string>  $attachmentIds
     */
    private function attachmentSummary(array $attachmentIds): string
    {
        if ($attachmentIds === []) {
            return '';
        }

        return ' Attachments included: '.implode(', ', $attachmentIds).'.';
    }

    /**
     * @param  array<int, string>  $attachmentIds
     */
    private function attachReferences(string $ticketId, array $attachmentIds, string $uploadedBy, string $visibility, ?CurrentUser $currentUser = null): void
    {
        $actor = $this->actor($currentUser);

        foreach ($attachmentIds as $attachmentId) {
            SupportTicketAttachment::query()->updateOrCreate(
                ['id' => $attachmentId],
                [
                    'support_ticket_id' => $ticketId,
                    'file_name' => $attachmentId.'.pdf',
                    'size' => 0,
                    'uploaded_by' => $uploadedBy,
                    'visibility' => $visibility,
                    'uploaded_at' => Carbon::now('UTC'),
                ],
            );

            $this->createActivity(
                ticketId: $ticketId,
                title: 'Attachment added',
                type: 'attachment-added',
                message: "Attachment {$attachmentId} added.",
                authorId: $actor->id,
                authorName: $uploadedBy,
                visibility: $visibility,
                isInternal: $visibility === 'internal',
                relatedEntityId: $attachmentId,
            );
        }
    }

    /**
     * @param  array<int, string>  $attachmentIds
     */
    private function attachActivityAttachments(string $activityId, string $ticketId, array $attachmentIds): void
    {
        $normalizedIds = collect($attachmentIds)
            ->map(fn ($id): string => trim((string) $id))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();

        if ($normalizedIds === []) {
            return;
        }

        $existingIds = SupportTicketAttachment::query()
            ->where('support_ticket_id', $ticketId)
            ->whereIn('id', $normalizedIds)
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

    private function actor(?CurrentUser $currentUser): CurrentUser
    {
        return $currentUser ?? new CurrentUser('amit', 'Amit', 'amit@example.com');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTicket(SupportTicket $ticket, bool $includeDetail = false): array
    {
        $data = [
            'id' => $ticket->id,
            'subject' => $ticket->subject,
            'submeta' => $ticket->submeta,
            'customer' => $ticket->customer,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'agent' => $ticket->agent ?? '',
            'updated' => $ticket->updated_at?->toIso8601ZuluString(),
            'requester' => $ticket->requester,
            'team' => $ticket->team,
            'source' => $ticket->source,
            'category' => $ticket->category,
            'isAssignedToMe' => $ticket->is_assigned_to_me,
            'isWaitingOnCustomer' => $ticket->is_waiting_on_customer,
            ...$this->operationalIndicators($ticket),
        ];

        if (! $includeDetail) {
            return $data;
        }

        return [
            ...$data,
            'slaFirstResponse' => $ticket->sla_first_response_at?->toIso8601ZuluString(),
            'slaResolution' => $ticket->sla_resolution_at?->toIso8601ZuluString(),
            'activities' => $ticket->activities->map(fn (SupportTicketActivity $activity): array => [
                'id' => $activity->id,
                'title' => $activity->title,
                'time' => $activity->occurred_at->toIso8601ZuluString(),
                'type' => $activity->type,
                'body' => $activity->message,
                'htmlBody' => $activity->html_body,
                'authorId' => $activity->author_id,
                'authorName' => $activity->author_name,
                'visibility' => $activity->visibility,
                'isInternal' => $activity->is_internal,
                'relatedEntityId' => $activity->related_entity_id,
                'parentActivityId' => $activity->parent_activity_id,
                'mentions' => $activity->mentions->map(fn (SupportTicketActivityMention $mention): array => [
                    'id' => $mention->mentioned_user_id ?? $mention->mentioned_team_id,
                    'display' => $mention->display_name,
                    'kind' => $mention->kind,
                ])->all(),
            ])->all(),
            'relatedItems' => $ticket->relatedItems->map(fn ($item): array => [
                'id' => $item->related_id,
                'title' => $item->title,
                'meta' => $item->meta,
            ])->all(),
            'forwardState' => $this->forwardState($ticket),
            'linkedTaskSummary' => $this->linkedTaskSummary($ticket),
            'attachmentSummary' => ['count' => $this->attachmentCount($ticket)],
            'linkedTasks' => $ticket->linkedTasks->map(fn (SupportTicketLinkedTask $task): array => $this->mapLinkedTask($task))->all(),
            'attachments' => $ticket->attachments->map(fn (SupportTicketAttachment $attachment): array => $this->mapAttachment($attachment))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function operationalIndicators(SupportTicket $ticket): array
    {
        $forwardState = $this->forwardState($ticket);
        $linkedTaskCount = $this->linkedTaskCount($ticket);
        $attachmentCount = $this->attachmentCount($ticket);

        return [
            'isForwarded' => $forwardState['lastMode'] !== null,
            'forwardMode' => $forwardState['lastMode'],
            'forwardTarget' => $forwardState['lastForwardedTo'],
            'hasLinkedTask' => $linkedTaskCount > 0,
            'linkedTaskCount' => $linkedTaskCount,
            'hasAttachments' => $attachmentCount > 0,
            'attachmentCount' => $attachmentCount,
            'waitingOn' => $this->waitingOn($ticket, $forwardState),
        ];
    }

    /**
     * @return array{lastMode: string|null, lastForwardedTo: string|null, lastForwardedAt: string|null, lastForwardedBy: string|null}
     */
    private function forwardState(SupportTicket $ticket): array
    {
        $activity = $ticket->relationLoaded('activities')
            ? $ticket->activities->first(fn (SupportTicketActivity $activity): bool => in_array($activity->type, ['forward', 'linked-task-created'], true))
            : $ticket->activities()->whereIn('type', ['forward', 'linked-task-created'])->latest('occurred_at')->first();

        if ($activity === null) {
            return ['lastMode' => null, 'lastForwardedTo' => null, 'lastForwardedAt' => null, 'lastForwardedBy' => null];
        }

        $mode = match ($activity->title) {
            'Ticket forwarded to team' => 'team',
            'Linked task created' => 'task',
            default => 'external',
        };

        return [
            'lastMode' => $mode,
            'lastForwardedTo' => $activity->related_entity_id,
            'lastForwardedAt' => $activity->occurred_at->toIso8601ZuluString(),
            'lastForwardedBy' => $activity->author_name,
        ];
    }

    /**
     * @return array{count: int, openCount: int}
     */
    private function linkedTaskSummary(SupportTicket $ticket): array
    {
        $tasks = $ticket->relationLoaded('linkedTasks') ? $ticket->linkedTasks : $ticket->linkedTasks()->get();

        return [
            'count' => $tasks->count(),
            'openCount' => $tasks->where('status', 'Open')->count(),
        ];
    }

    private function linkedTaskCount(SupportTicket $ticket): int
    {
        return (int) ($ticket->linked_tasks_count ?? ($ticket->relationLoaded('linkedTasks') ? $ticket->linkedTasks->count() : $ticket->linkedTasks()->count()));
    }

    private function attachmentCount(SupportTicket $ticket): int
    {
        return (int) ($ticket->attachments_count ?? ($ticket->relationLoaded('attachments') ? $ticket->attachments->count() : $ticket->attachments()->count()));
    }

    /**
     * @param  array<string, string|null>  $forwardState
     */
    private function waitingOn(SupportTicket $ticket, array $forwardState): ?string
    {
        if ($ticket->is_waiting_on_customer) {
            return 'customer';
        }

        return match ($forwardState['lastMode']) {
            'team', 'task' => 'team',
            'external' => 'external',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLinkedTask(SupportTicketLinkedTask $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'assignee' => $task->assignee,
            'status' => $task->status,
            'createdAt' => $task->created_at?->toIso8601ZuluString(),
            'updatedAt' => $task->updated_at?->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAttachment(SupportTicketAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'fileName' => $attachment->file_name,
            'size' => $attachment->size,
            'uploadedBy' => $attachment->uploaded_by,
            'uploadedAt' => $attachment->uploaded_at?->toIso8601ZuluString(),
            'visibility' => $attachment->visibility,
            'ticketId' => $attachment->support_ticket_id,
            'ticketSubject' => $attachment->ticket?->subject,
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{items: array<int, array<string, mixed>>, total: int, page: int, pageSize: int}
     */
    private function paginatedAttachments(array $query): array
    {
        $page = (int) ($query['page'] ?? 1);
        $pageSize = (int) ($query['pageSize'] ?? 20);
        $attachments = SupportTicketAttachment::query()
            ->with('ticket')
            ->when($query['ticketId'] ?? null, fn ($builder, string $ticketId) => $builder->where('support_ticket_id', $ticketId))
            ->when($query['visibility'] ?? null, fn ($builder, string $visibility) => $builder->where('visibility', $visibility))
            ->when($query['search'] ?? null, function ($builder, string $search): void {
                $builder->where(function ($builder) use ($search): void {
                    $builder
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('file_name', 'like', "%{$search}%")
                        ->orWhere('uploaded_by', 'like', "%{$search}%")
                        ->orWhereHas('ticket', fn ($ticketQuery) => $ticketQuery->where('subject', 'like', "%{$search}%"));
                });
            });

        $total = (clone $attachments)->count();

        return [
            'items' => $attachments
                ->latest('uploaded_at')
                ->offset(($page - 1) * $pageSize)
                ->limit($pageSize)
                ->get()
                ->map(fn (SupportTicketAttachment $attachment): array => $this->mapAttachment($attachment))
                ->all(),
            'total' => $total,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }
}
