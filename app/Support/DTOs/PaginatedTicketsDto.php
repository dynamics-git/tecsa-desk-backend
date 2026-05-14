<?php

namespace App\Support\DTOs;

final readonly class PaginatedTicketsDto
{
    /**
     * @param  array<int, TicketListItemDto>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $pageSize,
    ) {}

    /**
     * @return array{items: array<int, array<string, string|bool>>, total: int, page: int, pageSize: int}
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (TicketListItemDto $item): array => $item->toArray(), $this->items),
            'total' => $this->total,
            'page' => $this->page,
            'pageSize' => $this->pageSize,
        ];
    }
}
