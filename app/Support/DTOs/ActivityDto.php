<?php

namespace App\Support\DTOs;

final readonly class ActivityDto
{
    public function __construct(
        public string $id,
        public string $title,
        public string $time,
        public string $type,
        public ?string $body = null,
        public ?string $authorId = null,
        public ?string $authorName = null,
        public ?string $authorEmail = null,
        public ?string $senderType = null,
        public string $visibility = 'public',
        public bool $isInternal = false,
        public ?string $relatedEntityId = null,
        public ?string $parentActivityId = null,
        public array $recipients = [],
        public array $mentions = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'time' => $this->time,
            'type' => $this->type,
            'body' => $this->body,
            'authorId' => $this->authorId,
            'authorName' => $this->authorName,
            'authorEmail' => $this->authorEmail,
            'senderType' => $this->senderType,
            'visibility' => $this->visibility,
            'isInternal' => $this->isInternal,
            'relatedEntityId' => $this->relatedEntityId,
            'parentActivityId' => $this->parentActivityId,
            'recipients' => $this->recipients,
            'mentions' => $this->mentions,
        ];
    }
}
