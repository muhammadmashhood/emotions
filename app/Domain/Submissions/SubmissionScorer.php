<?php

namespace App\Domain\Submissions;

use App\Domain\Scoring\ScoringEngine;
use App\Domain\Scoring\ScoringMapping;
use App\Domain\Typeform\Data\NormalizedAnswer;
use App\Models\Answer;
use App\Models\FormVersion;
use App\Models\Submission;
use App\Models\SubmissionSectionScore;

/**
 * Runs the (pure) scoring engine and persists answers, section scores and totals for a submission.
 */
class SubmissionScorer
{
    public function __construct(private readonly ScoringEngine $engine) {}

    /**
     * @param  list<NormalizedAnswer>  $answers
     */
    public function scoreAndPersist(Submission $submission, FormVersion $version, array $answers, ?ScoringMapping $mapping = null): void
    {
        $mapping ??= ScoringMapping::fromVersion($version);
        $result = $this->engine->score($mapping, $answers, $submission->form->settings);

        $submission->answers()->delete();
        $rows = [];
        $now = now();
        foreach ($answers as $answer) {
            $field = $mapping->field($answer->fieldId);
            $rows[] = [
                'submission_id' => $submission->id,
                'typeform_field_id' => $answer->fieldId,
                'form_field_id' => $field?->id,
                'answer_type' => $answer->answerType,
                'value_text' => $answer->valueText,
                'value_number' => $answer->valueNumber,
                'value_json' => $answer->valueJson === null ? null : json_encode($answer->valueJson, JSON_UNESCAPED_UNICODE),
                'points' => $result->pointsFor($answer->fieldId),
                'max_points' => $result->maxPointsFor($answer->fieldId),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Answer::query()->insert($chunk);
        }

        $submission->sectionScores()->delete();
        $sectionRows = [];
        foreach ($result->sections as $section) {
            $sectionRows[] = [
                'submission_id' => $submission->id,
                'section_id' => $section->sectionId,
                'section_name' => $section->name,
                'display_order' => $section->displayOrder,
                'points' => $section->points,
                'max_points' => $section->maxPoints,
                'pct' => $section->pct,
                'weight' => $section->weight,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if ($sectionRows !== []) {
            SubmissionSectionScore::query()->insert($sectionRows);
        }

        $submission->forceFill([
            'form_version_id' => $version->id,
            'points' => $result->points,
            'max_points' => $result->maxPoints,
            'overall_pct' => $result->overallPct,
            'rag' => $result->rag,
            'scored_at' => $now,
        ])->save();
    }
}
