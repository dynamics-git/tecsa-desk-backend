<?php

namespace App\Support\DTOs;

final readonly class RelatedItemDto
{
    public function __construct(
        public string $id,
        public string $title,
        public string $meta,
    ) {}

    /**
     * @return array{id: string, title: string, meta: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'meta' => $this->meta,
        ];
    }
}
