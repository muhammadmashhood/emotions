<?php

namespace App\Domain\Scoring;

final readonly class MappedSection
{
    public function __construct(
        public int $id,
        public string $name,
        public int $displayOrder,
        public float $weight,
    ) {}
}
