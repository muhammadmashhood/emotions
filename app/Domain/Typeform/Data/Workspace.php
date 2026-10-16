<?php

namespace App\Domain\Typeform\Data;

final readonly class Workspace
{
    public function __construct(public string $id, public string $name, public bool $default = false) {}

    public static function fromApi(array $item): self
    {
        return new self((string) $item['id'], (string) ($item['name'] ?? 'Workspace'), (bool) ($item['default'] ?? false));
    }
}
