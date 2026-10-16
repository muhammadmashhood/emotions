<?php

namespace App\Domain\Scoring;

use App\Domain\Forms\FormSettings;
use App\Domain\Typeform\Data\NormalizedAnswer;
use App\Enums\Rag;

/**
 * Pure: mapping + normalised answers + settings → ScoreResult (spec section 7). No database, no HTTP.
 * Our mapping is the only source of truth; Typeform's own `calculated.score` is ignored.
 */
class ScoringEngine
{
    /**
     * @param  list<NormalizedAnswer>  $answers
     */
    public function score(ScoringMapping $mapping, array $answers, FormSettings $settings): ScoreResult
    {
        $answerPoints = [];
        /** @var array<int, array{points: float, max: float}> $totals */
        $totals = [];

        foreach ($answers as $answer) {
            $field = $mapping->field($answer->fieldId);
            if ($field === null) {
                $answerPoints[$answer->fieldId] = null;

                continue;
            }

            $score = $this->pointsFor($field, $answer, $settings);
            $answerPoints[$answer->fieldId] = $score;

            if ($score !== null) {
                $totals[$field->sectionId] ??= ['points' => 0.0, 'max' => 0.0];
                $totals[$field->sectionId]['points'] += $score['points'];
                $totals[$field->sectionId]['max'] += $score['max_points'];
            }
        }

        $sections = [];
        $weightedSum = 0.0;
        $weightTotal = 0.0;
        $points = 0.0;
        $maxPoints = 0.0;

        foreach ($mapping->sections as $section) {
            $t = $totals[$section->id] ?? ['points' => 0.0, 'max' => 0.0];
            $pct = $t['max'] > 0 ? round(100 * $t['points'] / $t['max'], 2) : null;
            $sections[] = new SectionScore($section->id, $section->name, $section->displayOrder, $section->weight, $t['points'], $t['max'], $pct);
            $points += $t['points'];
            $maxPoints += $t['max'];

            if ($pct !== null && $section->weight > 0) {
                $weightedSum += $section->weight * $pct;
                $weightTotal += $section->weight;
            }
        }

        $overall = $weightTotal > 0 ? round($weightedSum / $weightTotal, 2) : null;

        return new ScoreResult(
            answerPoints: $answerPoints,
            sections: $sections,
            points: $points,
            maxPoints: $maxPoints,
            overallPct: $overall,
            rag: Rag::fromPct($overall, $settings->ragGreenMin, $settings->ragAmberMin),
        );
    }

    /**
     * @return array{points: float, max_points: float}|null
     */
    public function pointsFor(MappedField $field, NormalizedAnswer $answer, FormSettings $settings): ?array
    {
        if (! $field->isScored) {
            return null;
        }

        if ($field->isScale()) {
            if ($answer->valueNumber === null) {
                return null;
            }
            $max = (float) ($field->scaleMax - $field->scaleMin);
            $points = min($max, max(0.0, $answer->valueNumber - $field->scaleMin));

            return ['points' => $points, 'max_points' => $max];
        }

        if ($field->type === 'yes_no' || $field->isYesNoChoice()) {
            if ($answer->isNotApplicable()) {
                return null;
            }
            if ($answer->isYes()) {
                return ['points' => $settings->yesNoPoints, 'max_points' => $settings->yesNoPoints];
            }
            if ($answer->isNo()) {
                return ['points' => 0.0, 'max_points' => $settings->yesNoPoints];
            }

            return null;
        }

        return null;
    }
}
