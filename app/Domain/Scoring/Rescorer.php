<?php

namespace App\Domain\Scoring;

use App\Domain\Audit\AuditLogger;
use App\Domain\Submissions\BranchResolver;
use App\Domain\Submissions\SubmissionScorer;
use App\Domain\Typeform\AnswerNormalizer;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Submission;

/**
 * Re-scores stored submissions from their raw answers (spec 7.4).
 */
class Rescorer
{
    public function __construct(
        private readonly AnswerNormalizer $normalizer,
        private readonly SubmissionScorer $scorer,
        private readonly AuditLogger $audit,
        private readonly BranchResolver $branches,
    ) {}

    /**
     * @param  FormVersion|null  $target  version to score against; null = the submission's own version
     */
    public function rescoreSubmission(Submission $submission, ?FormVersion $target = null, ?ScoringMapping $mapping = null): void
    {
        $version = $target ?? $submission->formVersion;
        $answers = $this->normalizer->normalizeAll(is_array($submission->raw['answers'] ?? null) ? $submission->raw['answers'] : []);

        $submission->loadMissing('form');
        $mapping ??= ScoringMapping::fromVersion($version);
        $hidden = is_array($submission->raw['hidden'] ?? null) ? $submission->raw['hidden'] : [];
        $submission->branch_id = $this->branches->resolve($submission->form, $mapping, $answers, $hidden);
        $this->scorer->scoreAndPersist($submission, $version, $answers, $mapping);
    }

    /**
     * @return int number of submissions re-scored
     */
    public function rescoreForm(Form $form, ?FormVersion $onlyVersion = null, bool $useLatestMapping = false): int
    {
        $latest = $form->currentVersion;
        $mappings = [];
        $count = 0;

        $query = $form->submissions()->with('formVersion')->orderBy('id');
        if ($onlyVersion !== null) {
            $query->where('form_version_id', $onlyVersion->id);
        }

        $query->chunkById(200, function ($submissions) use (&$count, &$mappings, $latest, $useLatestMapping, $form): void {
            foreach ($submissions as $submission) {
                $version = $useLatestMapping && $latest !== null ? $latest : $submission->formVersion;
                $mappings[$version->id] ??= ScoringMapping::fromVersion($version);
                $submission->setRelation('form', $form);
                $this->rescoreSubmission($submission, $version, $mappings[$version->id]);
                $count++;
            }
        });

        $this->audit->log('form.rescored', $form, [
            'count' => $count,
            'version' => $onlyVersion?->version_number,
            'use_latest_mapping' => $useLatestMapping,
        ], $form->hospital_id);

        return $count;
    }
}
