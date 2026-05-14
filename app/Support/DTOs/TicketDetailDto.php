<?php

namespace App\Support\DTOs;

final readonly class TicketDetailDto
{
    /**
     * @param  array<int, ActivityDto>  $activities
     * @param  array<int, RelatedItemDto>  $relatedItems
     */
    public function __construct(
        public TicketListItemDto $summary,
        public string $slaFirstResponse,
        public string $slaResolution,
        public array $activities,
        public array $relatedItems,
        public array $forwardState,
        public array $linkedTaskSummary,
        public array $attachmentSummary,
        public array $linkedTasks,
        public array $attachments,
    ) {}

    /**
     * @param  array<string, mixed>  $ticket
     */
    public static function fromArray(array $ticket): self
    {
        return new self(
            summary: TicketListItemDto::fromArray($ticket),
            slaFirstResponse: $ticket['slaFirstResponse'],
            slaResolution: $ticket['slaResolution'],
            activities: array_map(
                fn (array $activity): ActivityDto => new ActivityDto(
                    id: $activity['id'],
                    title: $activity['title'],
                    time: $activity['time'],
                    type: $activity['type'],
                    body: $activity['body'] ?? null,
                    authorId: $activity['authorId'] ?? null,
                    authorName: $activity['authorName'] ?? null,
                    visibility: $activity['visibility'] ?? 'public',
                    isInternal: $activity['isInternal'] ?? false,
                    relatedEntityId: $activity['relatedEntityId'] ?? null,
                    parentActivityId: $activity['parentActivityId'] ?? null,
                    mentions: $activity['mentions'] ?? [],
                ),
                $ticket['activities'],
            ),
            relatedItems: array_map(
                fn (array $item): RelatedItemDto => new RelatedItemDto($item['id'], $item['title'], $item['meta']),
                $ticket['relatedItems'],
            ),
            forwardState: $ticket['forwardState'],
            linkedTaskSummary: $ticket['linkedTaskSummary'],
            attachmentSummary: $ticket['attachmentSummary'],
            linkedTasks: $ticket['linkedTasks'],
            attachments: $ticket['attachments'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            ...$this->summary->toArray(),
            'slaFirstResponse' => $this->slaFirstResponse,
            'slaResolution' => $this->slaResolution,
            'activities' => array_map(fn (ActivityDto $activity): array => $activity->toArray(), $this->activities),
            'relatedItems' => array_map(fn (RelatedItemDto $item): array => $item->toArray(), $this->relatedItems),
            'forwardState' => $this->forwardState,
            'linkedTaskSummary' => $this->linkedTaskSummary,
            'attachmentSummary' => $this->attachmentSummary,
            'linkedTasks' => $this->linkedTasks,
            'attachments' => $this->attachments,
        ];
    }
}
