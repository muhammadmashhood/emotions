<?php

namespace App\Domain\Submissions;

use App\Domain\Forms\FormVersioner;
use App\Domain\Scoring\ScoringMapping;
use App\Domain\Typeform\AnswerNormalizer;
use App\Domain\Typeform\Data\NormalizedAnswer;
use App\Domain\Typeform\Exceptions\TypeformException;
use App\Enums\SubmissionSource;
use App\Enums\SubmissionStatus;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Submission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Idempotent create / update of a submission from a webhook or Responses API payload (spec 5.7).
 */
class SubmissionIngestor
{
    private const REFRESH_THROTTLE_SECONDS = 300;

    public function __construct(
        private readonly AnswerNormalizer $normalizer,
        private readonly SubmissionScorer $scorer,
        private readonly FormVersioner $versioner,
        private readonly BranchResolver $branches,
    ) {}

    /**
     * @param  array<string, mixed>  $response  the `form_response` object (webhook) or a Responses API item
     */
    public function ingest(Form $form, array $response, SubmissionSource $source, ?string $eventType = null): IngestOutcome
    {
        $token = (string) ($response['token'] ?? '');
        if ($token === '') {
            throw new RuntimeException('Response has no token.');
        }

        $submittedAt = $this->timestamp($response['submitted_at'] ?? null);
        $isPartial = ($eventType !== null && $eventType !== 'form_response') || $submittedAt === null;

        $lock = Cache::lock("ingest:{$form->id}:{$token}", 15);

        return $lock->block(10, function () use ($form, $response, $source, $eventType, $token, $submittedAt, $isPartial): IngestOutcome {
            $existing = Submission::query()->where('form_id', $form->id)->where('response_token', $token)->first();

            if ($existing !== null && ! ($existing->is_partial && ! $isPartial)) {
                return new IngestOutcome(IngestOutcome::DUPLICATE, $existing);
            }

            $answers = $this->normalizer->normalizeAll(is_array($response['answers'] ?? null) ? $response['answers'] : []);
            $version = $this->resolveVersion($form, $answers);
            $mapping = ScoringMapping::fromVersion($version);
            $hidden = is_array($response['hidden'] ?? null) ? $response['hidden'] : [];
            $info = InfoExtractor::extract($mapping, $answers, $hidden);

            $hospital = $form->hospital;
            $status = $hospital->require_review ? SubmissionStatus::PendingReview : SubmissionStatus::Approved;

            $submission = DB::transaction(function () use ($form, $existing, $response, $source, $eventType, $token, $submittedAt, $isPartial, $version, $answers, $mapping, $info, $hidden, $status): Submission {
                $submission = $existing ?? new Submission;
                $submission->fill([
                    'form_id' => $form->id,
                    'form_version_id' => $version->id,
                    'hospital_id' => $form->hospital_id,
                    'response_token' => $token,
                    'submitted_at' => $submittedAt,
                    'landed_at' => $this->timestamp($response['landed_at'] ?? null),
                    'is_partial' => $isPartial,
                    'status' => $existing->status ?? $status,
                    'source' => $existing->source ?? $source,
                    'raw' => [
                        'answers' => $response['answers'] ?? [],
                        'hidden' => $hidden,
                        'variables' => $response['variables'] ?? [],
                        'calculated' => $response['calculated'] ?? [],
                        'event_type' => $eventType,
                    ],
                    'info' => $info,
                    'branch_id' => $this->branches->resolve($form, $mapping, $answers, $hidden),
                ]);
                $submission->save();

                $submission->setRelation('form', $form);
                $this->scorer->scoreAndPersist($submission, $version, $answers, $mapping);

                return $submission;
            });

            return new IngestOutcome($existing ? IngestOutcome::UPDATED : IngestOutcome::CREATED, $submission);
        });
    }

    /**
     * Current version, refreshed from Typeform when the payload contains unknown field ids (max once per 5 min per form).
     *
     * @param  list<NormalizedAnswer>  $answers
     */
    private function resolveVersion(Form $form, array $answers): FormVersion
    {
        $version = $form->currentVersion;
        if ($version === null) {
            throw new RuntimeException("Form {$form->id} has no current version.");
        }

        $known = $version->fields()->pluck('typeform_field_id')->flip();
        $unknown = array_filter($answers, fn (NormalizedAnswer $a) => ! $known->has($a->fieldId));

        if ($unknown === []) {
            return $version;
        }

        $throttleKey = "form:{$form->id}:structure-refresh";
        if (Cache::has($throttleKey)) {
            return $version;
        }
        Cache::put($throttleKey, true, self::REFRESH_THROTTLE_SECONDS);

        try {
            $new = $this->versioner->refreshFromTypeform($form);
        } catch (TypeformException $e) {
            Log::warning('typeform.structure_refresh_failed', ['form_id' => $form->id, 'error' => $e->getMessage()]);

            return $version;
        }

        return $new ?? $version;
    }

    private function timestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
