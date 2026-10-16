<?php

namespace App\Livewire\Hospital\Evaluations;

use App\Domain\Audit\AuditLogger;
use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\DashboardQuery;
use App\Domain\Scoring\Rescorer;
use App\Enums\SubmissionStatus;
use App\Models\Answer;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
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
     * Answer-level facts for the visit: outcome mix, best and weakest answers, comments, duration.
     *
     * @param  Collection<int, mixed>  $sections
     * @return array<string, mixed>
     */
    private function insights($sections): array
    {
        $scored = [];
        $comments = [];
        $unscored = 0;
        foreach ($sections as $block) {
            foreach ($block['rows'] as $row) {
                $answer = $row['answer'];
                if ($answer->isScored() && $answer->max_points > 0) {
                    $scored[] = ['title' => $row['field']->title, 'section' => $block['section']->name, 'points' => $answer->points, 'max' => $answer->max_points, 'ratio' => $answer->points / $answer->max_points, 'value' => $answer->value_text];
                } else {
                    $unscored++;
                    if ($row['field']->type === 'long_text' && trim((string) $answer->value_text) !== '') {
                        $comments[] = ['section' => $block['section']->name, 'text' => $answer->value_text];
                    }
                }
            }
        }

        $full = count(array_filter($scored, fn ($a) => $a['ratio'] >= 0.999));
        $zero = count(array_filter($scored, fn ($a) => $a['ratio'] <= 0.001));
        usort($scored, fn ($a, $b) => $b['ratio'] <=> $a['ratio']);

        $landed = $this->evaluation->landed_at;
        $submitted = $this->evaluation->submitted_at;

        return [
            'scored_count' => count($scored),
            'full' => $full,
            'partial' => count($scored) - $full - $zero,
            'zero' => $zero,
            'unscored' => $unscored,
            'best' => array_slice($scored, 0, 3),
            'worst' => array_slice(array_reverse($scored), 0, 3),
            'comments' => $comments,
            'duration_minutes' => $landed && $submitted ? max(0, (int) round($landed->diffInMinutes($submitted))) : null,
        ];
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
        $orphanAnswers = $this->evaluation->answers->reject(fn ($a) => in_array($a->typeform_field_id, $knownFieldIds, true))->values();

        return view('livewire.hospital.evaluations.show', [
            'sections' => $sections,
            'orphanAnswers' => $orphanAnswers,
            'benchmark' => $this->benchmark($user),
            'insights' => $this->insights($sections),
            'isAdmin' => $user->isSuperAdmin(),
            'rawJson' => $user->isSuperAdmin() ? json_encode($this->evaluation->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'backUrl' => $user->isSuperAdmin() ? route('hospitals.evaluations.index', $this->evaluation->form->hospital) : route('evaluations.index'),
        ]);
    }
}
