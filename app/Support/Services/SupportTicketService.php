<?php

namespace App\Support\Services;

use App\Models\SupportTicketActivity;
use App\Support\Auth\CurrentUser;
use App\Support\DTOs\PaginatedTicketsDto;
use App\Support\DTOs\TicketDetailDto;
use App\Support\DTOs\TicketListItemDto;
use App\Support\Repositories\SupportTicketRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

final readonly class SupportTicketService
{
    public function __construct(
        private SupportTicketRepositoryInterface $tickets,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function list(array $query, ?CurrentUser $currentUser = null): PaginatedTicketsDto
    {
        $page = (int) ($query['page'] ?? 1);
        $pageSize = (int) ($query['pageSize'] ?? 20);
        $records = $this->tickets->search($query, $query['sort'] ?? null, $currentUser);
        $pageItems = array_slice($records, ($page - 1) * $pageSize, $pageSize);

        return new PaginatedTicketsDto(
            items: array_map(fn (array $ticket): TicketListItemDto => TicketListItemDto::fromArray($ticket), $pageItems),
            total: count($records),
            page: $page,
            pageSize: $pageSize,
        );
    }

    public function detail(string $id, ?CurrentUser $currentUser = null): ?TicketDetailDto
    {
        $ticket = $this->tickets->find($id, $currentUser);

        return $ticket === null ? null : TicketDetailDto::fromArray($ticket);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?CurrentUser $currentUser = null): array
    {
        return $this->tickets->create($payload, $currentUser);
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function linkedTasks(string $id): ?array
    {
        return $this->tickets->linkedTasks($id);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function attachments(array $query): array
    {
        return $this->tickets->attachments($query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function ticketAttachments(string $id, array $query): ?array
    {
        return $this->tickets->ticketAttachments($id, $query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function uploadAttachment(UploadedFile $file, array $payload, ?CurrentUser $currentUser = null): array
    {
        return $this->tickets->uploadAttachment($file, $payload, $currentUser);
    }

    public function deleteAttachment(string $attachmentId, ?CurrentUser $currentUser = null): bool
    {
        return $this->tickets->deleteAttachment($attachmentId, $currentUser);
    }

    /**
     * @param  array<int, string>  $ticketIds
     * @return array{success: true, updatedCount: int, tickets: array<int, array<string, mixed>>}
     */
    public function assign(array $ticketIds, string $agent, ?CurrentUser $currentUser = null): array
    {
        $updatedCount = $this->tickets->assign($ticketIds, $agent, $currentUser);

        return [
            'success' => true,
            'updatedCount' => $updatedCount,
            'tickets' => $this->syncPayloads($ticketIds, $currentUser),
        ];
    }

    /**
     * @param  array<int, string>  $ticketIds
     * @return array{success: true, updatedCount: int, tickets: array<int, array<string, mixed>>}
     */
    public function updateStatus(array $ticketIds, string $status, ?CurrentUser $currentUser = null): array
    {
        $updatedCount = $this->tickets->updateStatus($ticketIds, $status, $currentUser);

        return [
            'success' => true,
            'updatedCount' => $updatedCount,
            'tickets' => $this->syncPayloads($ticketIds, $currentUser),
        ];
    }

    /**
     * @param  array<int, string>  $ticketIds
     * @return array{success: true, updatedCount: int, tickets: array<int, array<string, mixed>>}
     */
    public function updatePriority(array $ticketIds, string $priority, ?CurrentUser $currentUser = null): array
    {
        $updatedCount = $this->tickets->updatePriority($ticketIds, $priority, $currentUser);

        return [
            'success' => true,
            'updatedCount' => $updatedCount,
            'tickets' => $this->syncPayloads($ticketIds, $currentUser),
        ];
    }

    /**
     * @return array{success: true, activityId: string, createdAt: string|null, ticket: array<string, mixed>|null}|null
     */
    public function reply(
        string $id,
        string $message,
        bool $isInternalNote,
        ?string $htmlBody = null,
        array $attachmentIds = [],
        ?string $parentActivityId = null,
        array $mentions = [],
        ?CurrentUser $currentUser = null,
    ): ?array {
        $activityId = $this->tickets->addReply($id, $message, $isInternalNote, $htmlBody, $attachmentIds, $parentActivityId, $mentions, $currentUser);

        if ($activityId === null) {
            return null;
        }

        $createdAt = SupportTicketActivity::query()->whereKey($activityId)->value('occurred_at');

        return [
            'success' => true,
            'activityId' => $activityId,
            'createdAt' => $createdAt !== null ? Carbon::parse((string) $createdAt)->toIso8601ZuluString() : null,
            'ticket' => $this->syncPayload($id, $currentUser),
        ];
    }

    /**
     * @return array{success: true, forwardId: string, linkedTaskId: string|null, message: string, ticket: array<string, mixed>|null}|null
     */
    public function forward(string $id, array $payload, ?CurrentUser $currentUser = null): ?array
    {
        $result = $this->tickets->forward($id, $payload, $currentUser);

        return $result === null
            ? null
            : [
                'success' => true,
                'forwardId' => $result['forwardId'],
                'linkedTaskId' => $result['linkedTaskId'],
                'message' => 'Ticket forwarded successfully',
                'ticket' => $this->syncPayload($id, $currentUser),
            ];
    }

    /**
     * @param  array<int, string>  $ticketIds
     * @return array<int, array<string, mixed>>
     */
    private function syncPayloads(array $ticketIds, ?CurrentUser $currentUser = null): array
    {
        $snapshots = [];

        foreach (array_values(array_unique($ticketIds)) as $ticketId) {
            $snapshot = $this->syncPayload($ticketId, $currentUser);
            if ($snapshot !== null) {
                $snapshots[] = $snapshot;
            }
        }

        return $snapshots;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function syncPayload(string $ticketId, ?CurrentUser $currentUser = null): ?array
    {
        $ticket = $this->tickets->find($ticketId, $currentUser);
        if ($ticket === null) {
            return null;
        }

        return [
            'id' => $ticket['id'],
            'ticketId' => $ticket['ticketId'] ?? $ticket['id'],
            'status' => $ticket['status'],
            'priority' => $ticket['priority'],
            'createdByType' => $ticket['createdByType'] ?? 'System',
            'updatedAt' => $ticket['updatedAt'] ?? $ticket['updated'] ?? null,
            'waitingOn' => $ticket['waitingOn'] ?? null,
            'slaState' => $ticket['slaState'] ?? 'unknown',
        ];
    }
}
