<?php

namespace App\Domain\Typeform\Data;

final readonly class FormListPage
{
    /** @param list<FormSummary> $items */
    public function __construct(
        public array $items,
        public int $totalItems,
        public int $pageCount,
    ) {}

    public static function fromApi(array $data, ?string $workspaceId = null, ?string $workspaceName = null): self
    {
        $items = array_map(
            fn (array $item) => FormSummary::fromApi($item, $workspaceId, $workspaceName),
            array_values($data['items'] ?? []),
        );

        return new self($items, (int) ($data['total_items'] ?? count($items)), (int) ($data['page_count'] ?? 1));
    }
}
