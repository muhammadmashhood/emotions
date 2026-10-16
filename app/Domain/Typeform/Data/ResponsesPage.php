<?php

namespace App\Domain\Typeform\Data;

final readonly class ResponsesPage
{
    /** @param list<array<string, mixed>> $items raw response items as returned by the API */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $pageCount,
    ) {}

    public static function fromApi(array $data): self
    {
        $items = array_values($data['items'] ?? []);

        return new self($items, (int) ($data['total_items'] ?? count($items)), (int) ($data['page_count'] ?? 1));
    }

    public function lastToken(): ?string
    {
        if ($this->items === []) {
            return null;
        }

        return $this->items[array_key_last($this->items)]['token'] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }
}
