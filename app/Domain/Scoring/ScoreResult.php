<?php

namespace App\Domain\Scoring;

use App\Enums\Rag;

final readonly class ScoreResult
{
    /**
     * @param  array<string, array{points: float, max_points: float}|null>  $answerPoints  keyed by Typeform field id; null = not scored
     * @param  list<SectionScore>  $sections
     */
    public function __construct(
        public array $answerPoints,
        public array $sections,
        public float $points,
        public float $maxPoints,
        public ?float $overallPct,
        public ?Rag $rag,
    ) {}

    public function pointsFor(string $typeformFieldId): ?float
    {
        return $this->answerPoints[$typeformFieldId]['points'] ?? null;
    }

    public function maxPointsFor(string $typeformFieldId): ?float
    {
        return $this->answerPoints[$typeformFieldId]['max_points'] ?? null;
    }
}
