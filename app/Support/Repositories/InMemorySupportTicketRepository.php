<?php

namespace App\Support\Repositories;

use App\Support\Auth\CurrentUser;
use App\Support\Enums\TicketStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class InMemorySupportTicketRepository implements SupportTicketRepositoryInterface
{
    /**
     * @var array<string, array<string, mixed>>|null
     */
    private static ?array $tickets = null;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, ?string $sort): array
    {
        $tickets = array_values($this->tickets());

        $tickets = array_values(array_filter($tickets, function (array $ticket) use ($filters): bool {
            return $this->matchesTextFilter($ticket, 'queue', $filters['queue'] ?? null, 'team')
                && $this->matchesTextFilter($ticket, 'priority', $filters['priority'] ?? null, 'priority')
                && $this->matchesTextFilter($ticket, 'status', $filters['status'] ?? null, 'status')
                && $this->matchesTextFilter($ticket, 'agent', $filters['agent'] ?? null, 'agent')
                && $this->matchesSearch($ticket, $filters['search'] ?? null);
        }));

        return $this->sort($tickets, $sort);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array
    {
        return $this->tickets()[$id] ?? null;
    }

    public function create(array $payload, ?CurrentUser $currentUser = null): array
    {
        $this->tickets();

        $ticketId = 'TK-'.(1049 + count(self::$tickets));
        $now = $this->now();
        self::$tickets[$ticketId] = [
            'id' => $ticketId,
            'ticketId' => $ticketId,
            'subject' => $payload['subject'],
            'submeta' => $payload['category'],
            'customer' => $payload['customer'],
            'priority' => $payload['priority'],
            'status' => 'Open',
            'agent' => 'Amit',
            'updated' => $now,
            'updatedAt' => $now,
            'requester' => $payload['requester'],
            'team' => $payload['team'],
            'source' => 'Customer Portal',
            'createdByType' => 'Agent',
            'category' => $payload['category'],
            'isAssignedToMe' => true,
            'isWaitingOnCustomer' => false,
            'slaState' => 'on_track',
            'slaFirstResponse' => $now,
            'slaResolution' => $now,
            'activities' => [],
            'relatedItems' => [],
            'forwardState' => ['lastMode' => null, 'lastForwardedTo' => null, 'lastForwardedAt' => null, 'lastForwardedBy' => null],
            'linkedTaskSummary' => ['count' => 0, 'openCount' => 0],
            'attachmentSummary' => ['count' => count($payload['attachmentIds'] ?? [])],
            'linkedTasks' => [],
            'attachments' => [],
        ];

        return [
            'success' => true,
            'id' => $ticketId,
            'ticketId' => $ticketId,
            'ticket' => self::$tickets[$ticketId],
        ];
    }

    public function linkedTasks(string $ticketId): ?array
    {
        $ticket = $this->find($ticketId);

        return $ticket === null ? null : ($ticket['linkedTasks'] ?? []);
    }

    public function attachments(array $query): array
    {
        return ['items' => [], 'total' => 0, 'page' => (int) ($query['page'] ?? 1), 'pageSize' => (int) ($query['pageSize'] ?? 20)];
    }

    public function ticketAttachments(string $ticketId, array $query): ?array
    {
        return $this->find($ticketId) === null ? null : $this->attachments([...$query, 'ticketId' => $ticketId]);
    }

    public function uploadAttachment(UploadedFile $file, array $payload, ?CurrentUser $currentUser = null): array
    {
        return [
            'id' => 'ATT-2001',
            'fileName' => $file->getClientOriginalName(),
            'size' => $file->getSize() ?: 0,
            'uploadedBy' => $payload['requester'] ?? 'Amit',
            'uploadedAt' => $this->now(),
            'visibility' => $payload['visibility'],
            'ticketId' => $payload['ticketId'] ?? null,
            'ticketSubject' => null,
        ];
    }

    public function assign(array $ticketIds, string $agent, ?CurrentUser $currentUser = null): int
    {
        $this->tickets();

        return $this->mutateTickets($ticketIds, function (array &$ticket) use ($agent): void {
            $ticket['agent'] = $agent;
            $ticket['isAssignedToMe'] = Str::lower($agent) === 'amit';
            $ticket['updated'] = $this->now();
            $ticket['updatedAt'] = $ticket['updated'];
            $ticket['activities'][] = [
                'title' => "{$agent} was assigned the ticket",
                'time' => $ticket['updated'],
                'type' => 'assignment',
            ];
        });
    }

    public function updateStatus(array $ticketIds, string $status, ?CurrentUser $currentUser = null): int
    {
        $this->tickets();

        return $this->mutateTickets($ticketIds, function (array &$ticket) use ($status): void {
            $ticket['status'] = $status;
            $ticket['isWaitingOnCustomer'] = $status === TicketStatus::PendingCustomer->value;
            $ticket['updated'] = $this->now();
            $ticket['updatedAt'] = $ticket['updated'];
            $ticket['activities'][] = [
                'title' => "Status changed to {$status}",
                'time' => $ticket['updated'],
                'type' => 'status',
            ];
        });
    }

    public function updatePriority(array $ticketIds, string $priority, ?CurrentUser $currentUser = null): int
    {
        $this->tickets();

        return $this->mutateTickets($ticketIds, function (array &$ticket) use ($priority): void {
            $ticket['priority'] = $priority;
            $ticket['updated'] = $this->now();
            $ticket['updatedAt'] = $ticket['updated'];
            $ticket['activities'][] = [
                'title' => "Priority changed to {$priority}",
                'time' => $ticket['updated'],
                'type' => 'priority',
            ];
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
        $this->tickets();

        if (! isset(self::$tickets[$ticketId])) {
            return null;
        }

        $activityId = 'ACT-'.self::$activitySequence++;
        $time = $this->now();

        self::$tickets[$ticketId]['updated'] = $time;
        self::$tickets[$ticketId]['updatedAt'] = $time;
        self::$tickets[$ticketId]['activities'][] = [
            'id' => $activityId,
            'title' => $isInternalNote ? 'Internal note added' : 'Reply sent to requester',
            'time' => $time,
            'type' => $isInternalNote ? 'note' : 'reply',
            'message' => $message,
            'htmlBody' => $htmlBody,
        ];

        return $activityId;
    }

    public function forward(string $ticketId, array $payload, ?CurrentUser $currentUser = null): ?array
    {
        $this->tickets();

        if (! isset(self::$tickets[$ticketId])) {
            return null;
        }

        $activityId = 'ACT-'.self::$activitySequence++;
        $time = $this->now();
        $mode = $payload['mode'];
        $comment = $payload['comment'] ?? null;
        $attachmentIds = $payload['attachmentIds'] ?? [];
        $linkedTaskId = null;

        if ($mode === 'team') {
            $teamName = str($payload['teamId'])->replace(['-', '_'], ' ')->headline()->toString();
            self::$tickets[$ticketId]['team'] = $teamName;
            $title = 'Ticket forwarded to team';
            $message = "Forwarded to {$teamName}".($comment ? " with internal comment: {$comment}" : '.');
        } elseif ($mode === 'task') {
            $linkedTaskId = 'TASK-2001';
            $assignee = $payload['taskAssignee'] ?? 'Unassigned';
            $title = 'Linked task created';
            $message = "Created linked task {$linkedTaskId} assigned to {$assignee}.".($comment ? " Comment: {$comment}" : '');
        } else {
            $title = 'Ticket forwarded';
            $message = "Forwarded to {$payload['to']}".($comment ? " with note: {$comment}" : '.');
        }

        if ($attachmentIds !== []) {
            $message .= ' Attachments included: '.implode(', ', $attachmentIds).'.';
        }

        self::$tickets[$ticketId]['updated'] = $time;
        self::$tickets[$ticketId]['updatedAt'] = $time;
        self::$tickets[$ticketId]['activities'][] = [
            'id' => $activityId,
            'title' => $title,
            'time' => $time,
            'type' => 'forward',
            'body' => $message,
            'authorName' => 'Amit',
            'visibility' => 'internal',
            'isInternal' => true,
        ];

        return ['forwardId' => $activityId, 'linkedTaskId' => $linkedTaskId];
    }

    private static int $activitySequence = 10001;

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tickets(): array
    {
        if (self::$tickets === null) {
            self::$tickets = $this->seedTickets();
        }

        return self::$tickets;
    }

    /**
     * @param  array<int, string>  $ticketIds
     * @param  callable(array<string, mixed>&): void  $callback
     */
    private function mutateTickets(array $ticketIds, callable $callback): int
    {
        $updated = 0;

        foreach (array_unique($ticketIds) as $ticketId) {
            if (! isset(self::$tickets[$ticketId])) {
                continue;
            }

            $callback(self::$tickets[$ticketId]);
            $updated++;
        }

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function matchesTextFilter(array $ticket, string $filterName, mixed $filterValue, string $ticketKey): bool
    {
        unset($filterName);

        if ($filterValue === null || $filterValue === '') {
            return true;
        }

        return Str::lower((string) $ticket[$ticketKey]) === Str::lower((string) $filterValue);
    }

    /**
     * @param  array<string, mixed>  $ticket
     */
    private function matchesSearch(array $ticket, mixed $search): bool
    {
        if ($search === null || $search === '') {
            return true;
        }

        $haystack = implode(' ', [
            $ticket['id'],
            $ticket['subject'],
            $ticket['submeta'],
            $ticket['customer'],
            $ticket['requester'],
            $ticket['team'],
            $ticket['source'],
            $ticket['category'],
        ]);

        return Str::contains(Str::lower($haystack), Str::lower((string) $search));
    }

    /**
     * @param  array<int, array<string, mixed>>  $tickets
     * @return array<int, array<string, mixed>>
     */
    private function sort(array $tickets, ?string $sort): array
    {
        $sort = $sort ?: 'updated_desc';
        $priorityRank = ['Urgent' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1];

        usort($tickets, function (array $left, array $right) use ($sort, $priorityRank): int {
            return match ($sort) {
                'updated_asc' => strcmp($left['updated'], $right['updated']),
                'priority_desc' => ($priorityRank[$right['priority']] ?? 0) <=> ($priorityRank[$left['priority']] ?? 0),
                'priority_asc' => ($priorityRank[$left['priority']] ?? 0) <=> ($priorityRank[$right['priority']] ?? 0),
                'status_asc' => strcmp($left['status'], $right['status']),
                'customer_asc' => strcmp($left['customer'], $right['customer']),
                'subject_asc' => strcmp($left['subject'], $right['subject']),
                default => strcmp($right['updated'], $left['updated']),
            };
        });

        return $tickets;
    }

    private function now(): string
    {
        return Carbon::now('UTC')->toIso8601ZuluString();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function seedTickets(): array
    {
        $tickets = [
            [
                'id' => 'TK-1048',
                'ticketId' => 'TK-1048',
                'subject' => 'Unable to submit claim after document upload',
                'submeta' => 'Portal / Attachment / Error',
                'customer' => 'HLIB',
                'priority' => 'High',
                'status' => 'Open',
                'agent' => 'Amit',
                'updated' => '2026-05-02T10:25:00Z',
                'updatedAt' => '2026-05-02T10:25:00Z',
                'requester' => 'Nur Aisyah',
                'team' => 'Portal Support',
                'source' => 'Customer Portal',
                'createdByType' => 'Customer',
                'category' => 'Claims / Attachments',
                'isAssignedToMe' => true,
                'isWaitingOnCustomer' => false,
                'slaState' => 'on_track',
                'slaFirstResponse' => '2026-05-02T11:00:00Z',
                'slaResolution' => '2026-05-02T17:30:00Z',
                'activities' => [
                    ['title' => 'Amit viewed the ticket', 'time' => '2026-05-02T10:25:00Z', 'type' => 'view'],
                    ['title' => 'Requester uploaded claim documents', 'time' => '2026-05-02T09:58:00Z', 'type' => 'attachment'],
                ],
                'relatedItems' => [
                    ['id' => 'TK-1029', 'title' => 'Claims upload issue', 'meta' => 'Resolved last week'],
                ],
            ],
            [
                'id' => 'TK-1047',
                'ticketId' => 'TK-1047',
                'subject' => 'Customer portal password reset email not received',
                'submeta' => 'Portal / Authentication',
                'customer' => 'Acme Health',
                'priority' => 'Medium',
                'status' => 'In Progress',
                'agent' => 'Priya',
                'updated' => '2026-05-02T09:40:00Z',
                'updatedAt' => '2026-05-02T09:40:00Z',
                'requester' => 'Jason Lee',
                'team' => 'Portal Support',
                'source' => 'Email',
                'createdByType' => 'Customer',
                'category' => 'Access / Password Reset',
                'isAssignedToMe' => false,
                'isWaitingOnCustomer' => false,
                'slaState' => 'on_track',
                'slaFirstResponse' => '2026-05-02T10:15:00Z',
                'slaResolution' => '2026-05-03T09:30:00Z',
                'activities' => [
                    ['title' => 'Priya started investigation', 'time' => '2026-05-02T09:40:00Z', 'type' => 'status'],
                ],
                'relatedItems' => [
                    ['id' => 'KB-220', 'title' => 'Password reset delivery checks', 'meta' => 'Knowledge base'],
                ],
            ],
            [
                'id' => 'TK-1046',
                'ticketId' => 'TK-1046',
                'subject' => 'Missing invoice in billing dashboard',
                'submeta' => 'Billing / Invoice',
                'customer' => 'Globex',
                'priority' => 'Low',
                'status' => 'Pending Customer',
                'agent' => 'Amit',
                'updated' => '2026-05-01T16:05:00Z',
                'updatedAt' => '2026-05-01T16:05:00Z',
                'requester' => 'Maria Gomez',
                'team' => 'Billing Support',
                'source' => 'Customer Portal',
                'createdByType' => 'Customer',
                'category' => 'Billing / Documents',
                'isAssignedToMe' => true,
                'isWaitingOnCustomer' => true,
                'slaState' => 'on_track',
                'slaFirstResponse' => '2026-05-01T17:00:00Z',
                'slaResolution' => '2026-05-04T17:00:00Z',
                'activities' => [
                    ['title' => 'Amit requested billing period details', 'time' => '2026-05-01T16:05:00Z', 'type' => 'reply'],
                ],
                'relatedItems' => [],
            ],
            [
                'id' => 'TK-1045',
                'ticketId' => 'TK-1045',
                'subject' => 'Urgent outage report for API callbacks',
                'submeta' => 'API / Webhook / Outage',
                'customer' => 'Northwind',
                'priority' => 'Urgent',
                'status' => 'Open',
                'agent' => 'Mei Lin',
                'updated' => '2026-05-02T10:10:00Z',
                'updatedAt' => '2026-05-02T10:10:00Z',
                'requester' => 'Daniel Tan',
                'team' => 'Integration Support',
                'source' => 'Phone',
                'createdByType' => 'Customer',
                'category' => 'API / Callbacks',
                'isAssignedToMe' => false,
                'isWaitingOnCustomer' => false,
                'slaState' => 'on_track',
                'slaFirstResponse' => '2026-05-02T10:20:00Z',
                'slaResolution' => '2026-05-02T13:00:00Z',
                'activities' => [
                    ['title' => 'Outage bridge created', 'time' => '2026-05-02T10:10:00Z', 'type' => 'incident'],
                ],
                'relatedItems' => [
                    ['id' => 'INC-88', 'title' => 'Callback latency incident', 'meta' => 'Active incident'],
                ],
            ],
        ];

        return collect($tickets)->keyBy('id')->all();
    }
}
