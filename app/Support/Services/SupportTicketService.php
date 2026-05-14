<?php

namespace App\Support\Services;

use App\Support\Auth\CurrentUser;
use App\Support\DTOs\PaginatedTicketsDto;
use App\Support\DTOs\TicketDetailDto;
use App\Support\DTOs\TicketListItemDto;
use App\Support\Repositories\SupportTicketRepositoryInterface;
use Illuminate\Http\UploadedFile;

final readonly class SupportTicketService
{
    public function __construct(
        private SupportTicketRepositoryInterface $tickets,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     */
    public function list(array $query): PaginatedTicketsDto
    {
        $page = (int) ($query['page'] ?? 1);
        $pageSize = (int) ($query['pageSize'] ?? 20);
        $records = $this->tickets->search($query, $query['sort'] ?? null);
        $pageItems = array_slice($records, ($page - 1) * $pageSize, $pageSize);

        return new PaginatedTicketsDto(
            items: array_map(fn (array $ticket): TicketListItemDto => TicketListItemDto::fromArray($ticket), $pageItems),
            total: count($records),
            page: $page,
            pageSize: $pageSize,
        );
    }

    public function detail(string $id): ?TicketDetailDto
    {
        $ticket = $this->tickets->find($id);

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

    /**
     * @param  array<int, string>  $ticketIds
     * @return array{success: true, updatedCount: int}
     */
    public function assign(array $ticketIds, string $agent, ?CurrentUser $currentUser = null): array
    {
        return ['success' => true, 'updatedCount' => $this->tickets->assign($ticketIds, $agent, $currentUser)];
    }

    /**
     * @param  array<int, string>  $ticketIds
     * @return array{success: true, updatedCount: int}
     */
    public function updateStatus(array $ticketIds, string $status, ?CurrentUser $currentUser = null): array
    {
        return ['success' => true, 'updatedCount' => $this->tickets->updateStatus($ticketIds, $status, $currentUser)];
    }

    /**
     * @param  array<int, string>  $ticketIds
     * @return array{success: true, updatedCount: int}
     */
    public function updatePriority(array $ticketIds, string $priority, ?CurrentUser $currentUser = null): array
    {
        return ['success' => true, 'updatedCount' => $this->tickets->updatePriority($ticketIds, $priority, $currentUser)];
    }

    /**
     * @return array{success: true, activityId: string}|null
     */
    public function reply(
        string $id,
        string $message,
        bool $isInternalNote,
        array $attachmentIds = [],
        ?string $parentActivityId = null,
        array $mentions = [],
        ?CurrentUser $currentUser = null,
    ): ?array {
        $activityId = $this->tickets->addReply($id, $message, $isInternalNote, $attachmentIds, $parentActivityId, $mentions, $currentUser);

        return $activityId === null ? null : ['success' => true, 'activityId' => $activityId];
    }

    /**
     * @return array{success: true, forwardId: string}|null
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
            ];
    }
}
