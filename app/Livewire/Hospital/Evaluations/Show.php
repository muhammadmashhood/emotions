<?php

namespace App\Livewire\Hospital\Evaluations;

use App\Domain\Audit\AuditLogger;
use App\Domain\Scoring\Rescorer;
use App\Enums\SubmissionStatus;
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
            'isAdmin' => $user->isSuperAdmin(),
            'rawJson' => $user->isSuperAdmin() ? json_encode($this->evaluation->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'backUrl' => $user->isSuperAdmin() ? route('hospitals.evaluations.index', $this->evaluation->form->hospital) : route('evaluations.index'),
        ]);
    }
}
