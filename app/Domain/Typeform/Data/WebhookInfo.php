<?php

namespace App\Domain\Typeform\Data;

final readonly class WebhookInfo
{
    public function __construct(
        public ?string $id,
        public string $tag,
        public string $url,
        public bool $enabled,
        public bool $verifySsl = true,
    ) {}

    public static function fromApi(array $data): self
    {
        return new self(
            id: isset($data['id']) ? (string) $data['id'] : null,
            tag: (string) ($data['tag'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            enabled: (bool) ($data['enabled'] ?? false),
            verifySsl: (bool) ($data['verify_ssl'] ?? true),
        );
    }
}
