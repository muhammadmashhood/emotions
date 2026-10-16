<?php

namespace App\Domain\Scoring;

final readonly class SectionScore
{
    public function __construct(
        public int $sectionId,
        public string $name,
        public int $displayOrder,
        public float $weight,
        public float $points,
        public float $maxPoints,
        public ?float $pct,
    ) {}
}
