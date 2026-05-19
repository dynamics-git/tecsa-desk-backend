<?php

namespace App\Support\DTOs;

final readonly class TicketListItemDto
{
    public function __construct(
        public string $id,
        public string $subject,
        public string $submeta,
        public string $customer,
        public string $priority,
        public string $status,
        public string $agent,
        public string $updated,
        public string $updatedAt,
        public string $requester,
        public string $team,
        public string $source,
        public string $createdByType,
        public string $category,
        public bool $isAssignedToMe,
        public bool $isWaitingOnCustomer,
        public bool $isForwarded,
        public ?string $forwardMode,
        public ?string $forwardTarget,
        public bool $hasLinkedTask,
        public int $linkedTaskCount,
        public bool $hasAttachments,
        public int $attachmentCount,
        public ?string $waitingOn,
        public string $slaState,
    ) {}

    /**
     * @param  array<string, mixed>  $ticket
     */
    public static function fromArray(array $ticket): self
    {
        return new self(
            id: $ticket['id'],
            subject: $ticket['subject'],
            submeta: $ticket['submeta'],
            customer: $ticket['customer'],
            priority: $ticket['priority'],
            status: $ticket['status'],
            agent: $ticket['agent'],
            updated: $ticket['updated'],
            updatedAt: $ticket['updatedAt'] ?? $ticket['updated'],
            requester: $ticket['requester'],
            team: $ticket['team'],
            source: $ticket['source'],
            createdByType: $ticket['createdByType'] ?? 'System',
            category: $ticket['category'],
            isAssignedToMe: $ticket['isAssignedToMe'],
            isWaitingOnCustomer: $ticket['isWaitingOnCustomer'],
            isForwarded: $ticket['isForwarded'] ?? false,
            forwardMode: $ticket['forwardMode'] ?? null,
            forwardTarget: $ticket['forwardTarget'] ?? null,
            hasLinkedTask: $ticket['hasLinkedTask'] ?? false,
            linkedTaskCount: $ticket['linkedTaskCount'] ?? 0,
            hasAttachments: $ticket['hasAttachments'] ?? false,
            attachmentCount: $ticket['attachmentCount'] ?? 0,
            waitingOn: $ticket['waitingOn'] ?? null,
            slaState: $ticket['slaState'] ?? 'unknown',
        );
    }

    /**
     * @return array<string, string|bool|int|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'submeta' => $this->submeta,
            'customer' => $this->customer,
            'priority' => $this->priority,
            'status' => $this->status,
            'agent' => $this->agent,
            'updated' => $this->updated,
            'updatedAt' => $this->updatedAt,
            'requester' => $this->requester,
            'team' => $this->team,
            'source' => $this->source,
            'createdByType' => $this->createdByType,
            'category' => $this->category,
            'isAssignedToMe' => $this->isAssignedToMe,
            'isWaitingOnCustomer' => $this->isWaitingOnCustomer,
            'isForwarded' => $this->isForwarded,
            'forwardMode' => $this->forwardMode,
            'forwardTarget' => $this->forwardTarget,
            'hasLinkedTask' => $this->hasLinkedTask,
            'linkedTaskCount' => $this->linkedTaskCount,
            'hasAttachments' => $this->hasAttachments,
            'attachmentCount' => $this->attachmentCount,
            'waitingOn' => $this->waitingOn,
            'slaState' => $this->slaState,
        ];
    }
}
