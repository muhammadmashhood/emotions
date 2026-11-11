<?php

namespace App\Livewire\Hospital\Evaluations;

use App\Domain\Audit\AuditLogger;
use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\DashboardQuery;
use App\Domain\Scoring\Rescorer;
use App\Enums\SubmissionStatus;
use App\Models\FormField;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Evaluation detail (spec 9.3). Route binding already applies tenant visibility (404 for other hospitals).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Submission $evaluation;

    public string $reviewNote = '';

    public function mount(Submission $evaluation): void
    {
        $this->authorize('view', $evaluation);
        $this->evaluation = $evaluation;
    }

    public function rescore(Rescorer $rescorer, AuditLogger $audit): void
    {
        $this->authorize('rescore', $this->evaluation);
        $rescorer->rescoreSubmission($this->evaluation);
        $audit->log('submission.rescored', $this->evaluation, [], $this->evaluation->hospital_id);
        $this->evaluation->refresh();
        session()->flash('status', 'Evaluation re-scored.');
    }

    public function approve(AuditLogger $audit): void
    {
        $this->review(SubmissionStatus::Approved, 'submission.approved', $audit);
    }

    public function reject(AuditLogger $audit): void
    {
        $this->review(SubmissionStatus::Rejected, 'submission.rejected', $audit);
    }

    /**
     * How this evaluation compares with the same survey over the last 12 months.
     *
     * @return array{overall_avg: float|null, count: int, percentile: int|null, sections: array<string, float>}
     */
    private function benchmark(User $user): array
    {
        $form = $this->evaluation->form;
        $query = new DashboardQuery($user);
        $filters = DashboardFilters::fromRequest('12m')->withForm($form->id);

        $headline = $query->headline($form->hospital, $filters);
        $sections = collect($query->sectionAverages($form, $filters))->mapWithKeys(fn ($s) => [$s['name'] => $s['avg']])->all();

        $percentile = null;
        if ($this->evaluation->overall_pct !== null && $headline['count'] > 1) {
            $below = $query->base($form->hospital, $filters)->where('submissions.overall_pct', '<', $this->evaluation->overall_pct)->count();
            $percentile = (int) round(100 * $below / $headline['count']);
        }

        return ['overall_avg' => $headline['avg'], 'count' => $headline['count'], 'percentile' => $percentile, 'sections' => $sections];
    }

    /**
     * Free-text questions that ask for remarks (not descriptive info such as doctor name or speciality).
     */
    public static function isCommentField(FormField $field): bool
    {
        if ($field->info_key !== null || $field->type !== 'long_text') {
            return false;
        }

        return (bool) preg_match('/comment|remark|feedback|suggest|observ|note|describe|explain|why|improve|experience/i', $field->title);
    }

    private function review(SubmissionStatus $status, string $action, AuditLogger $audit): void
    {
        $this->authorize('review', $this->evaluation);
        /** @var User $user */
        $user = auth()->user();

        $this->evaluation->forceFill([
            'status' => $status,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => trim($this->reviewNote) !== '' ? trim($this->reviewNote) : null,
        ])->save();

        $audit->log($action, $this->evaluation, ['note' => $this->evaluation->review_note], $this->evaluation->hospital_id, $user);
        $this->reviewNote = '';
        session()->flash('status', $status === SubmissionStatus::Approved ? 'Evaluation approved.' : 'Evaluation rejected.');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $this->evaluation->load(['form.hospital', 'formVersion', 'branch', 'sectionScores', 'answers.field', 'reviewer']);

        $fieldsBySection = $this->evaluation->formVersion->fields()->with('section')->get()->groupBy('section_id');
        $answersByField = $this->evaluation->answers->keyBy('typeform_field_id');
        $sectionScores = $this->evaluation->sectionScores->keyBy('section_id');

        $sections = $this->evaluation->formVersion->sections()->get()->map(function ($section) use ($fieldsBySection, $answersByField, $sectionScores) {
            $rows = [];
            foreach ($fieldsBySection->get($section->id, collect())->sortBy('display_order') as $field) {
                $answer = $answersByField->get($field->typeform_field_id);
                if ($answer === null) {
                    continue;
                }
                $rows[] = ['field' => $field, 'answer' => $answer];
            }

            return ['section' => $section, 'score' => $sectionScores->get($section->id), 'rows' => $rows];
        });

        $knownFieldIds = $this->evaluation->formVersion->fields()->pluck('typeform_field_id')->all();
        $scorableSectionIds = $this->evaluation->formVersion->fields()->reorder()->where('is_scored', true)->distinct()->pluck('section_id')->map(fn ($id) => (int) $id)->all();
        $orphanAnswers = $this->evaluation->answers->reject(fn ($a) => in_array($a->typeform_field_id, $knownFieldIds, true))->values();

        return view('livewire.hospital.evaluations.show', [
            'sections' => $sections,
            'orphanAnswers' => $orphanAnswers,
            'benchmark' => $this->benchmark($user),
            'journeyScores' => $this->evaluation->sectionScores->filter(fn ($s) => in_array((int) $s->section_id, $scorableSectionIds, true))->values(),
            'isAdmin' => $user->isEmotionStaff(),
            'rawJson' => $user->isEmotionStaff() ? json_encode($this->evaluation->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'backUrl' => $user->isEmotionStaff() ? route('hospitals.evaluations.index', $this->evaluation->form->hospital) : route('evaluations.index'),
        ]);
    }
}
