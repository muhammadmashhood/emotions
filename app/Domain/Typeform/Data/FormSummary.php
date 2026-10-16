<?php

namespace App\Domain\Typeform\Data;

final readonly class FormSummary
{
    public function __construct(
        public string $id,
        public string $title,
        public ?string $lastUpdatedAt,
        public ?string $workspaceId = null,
        public ?string $workspaceName = null,
    ) {}

    public static function fromApi(array $item, ?string $workspaceId = null, ?string $workspaceName = null): self
    {
        return new self(
            id: (string) $item['id'],
            title: (string) ($item['title'] ?? 'Untitled'),
            lastUpdatedAt: $item['last_updated_at'] ?? null,
            workspaceId: $workspaceId,
            workspaceName: $workspaceName,
        );
    }
}
