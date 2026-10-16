<?php

namespace App\Domain\Typeform\Data;

final readonly class ParsedField
{
    /**
     * @param  list<array{id: string, ref: string|null, label: string}>|null  $choices
     */
    public function __construct(
        public string $id,
        public ?string $ref,
        public string $title,
        public ?string $description,
        public string $type,
        public bool $isScored,
        public ?int $scaleMin,
        public ?int $scaleMax,
        public ?array $choices,
        public ?string $infoKey,
        public int $displayOrder,
    ) {}

    public function withInfoKey(?string $infoKey): self
    {
        return new self($this->id, $this->ref, $this->title, $this->description, $this->type, $this->isScored, $this->scaleMin, $this->scaleMax, $this->choices, $infoKey, $this->displayOrder);
    }
}
