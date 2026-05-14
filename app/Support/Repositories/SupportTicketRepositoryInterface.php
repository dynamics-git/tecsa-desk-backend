<?php

namespace App\Support\Repositories;

use App\Support\Auth\CurrentUser;
use Illuminate\Http\UploadedFile;

interface SupportTicketRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function search(array $filters, ?string $sort): array;

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $id): ?array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, ?CurrentUser $currentUser = null): array;

    /**
     * @return array<int, array<string, mixed>>|null
     */
    public function linkedTasks(string $ticketId): ?array;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function attachments(array $query): array;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function ticketAttachments(string $ticketId, array $query): ?array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function uploadAttachment(UploadedFile $file, array $payload, ?CurrentUser $currentUser = null): array;

    /**
     * @param  array<int, string>  $ticketIds
     */
    public function assign(array $ticketIds, string $agent, ?CurrentUser $currentUser = null): int;

    /**
     * @param  array<int, string>  $ticketIds
     */
    public function updateStatus(array $ticketIds, string $status, ?CurrentUser $currentUser = null): int;

    /**
     * @param  array<int, string>  $ticketIds
     */
    public function updatePriority(array $ticketIds, string $priority, ?CurrentUser $currentUser = null): int;

    /**
     * @param  array<int, string>  $attachmentIds
     */
    /**
     * @param  array<int, string>  $attachmentIds
     * @param  array<int, array{id: string, kind: string, display: string}>  $mentions
     */
    public function addReply(
        string $ticketId,
        string $message,
        bool $isInternalNote,
        array $attachmentIds = [],
        ?string $parentActivityId = null,
        array $mentions = [],
        ?CurrentUser $currentUser = null,
    ): ?string;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{forwardId: string, linkedTaskId: string|null}|null
     */
    public function forward(string $ticketId, array $payload, ?CurrentUser $currentUser = null): ?array;
}
