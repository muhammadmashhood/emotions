<?php

namespace App\Livewire\Hospital\Evaluations;

use App\Domain\Audit\AuditLogger;
use App\Domain\Dashboard\DashboardFilters;
use App\Domain\Dashboard\DashboardQuery;
use App\Domain\Scoring\Rescorer;
use App\Domain\Submissions\AttachmentStore;
use App\Enums\SubmissionStatus;
use App\Models\FormField;
use App\Models\Submission;
use App\Models\SubmissionAttachment;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Evaluation detail (spec 9.3). Route binding already applies tenant visibility (404 for other hospitals).
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Submission $evaluation;

    public string $reviewNote = '';

    /** Staff edit mode (spec: memo item 10). */
    public bool $editing = false;

    /** @var array<string, string> points per Typeform field id, as typed */
    public array $editPoints = [];

    /** @var array<string, string> comment text per Typeform field id */
    public array $editText = [];

    /** @var list<TemporaryUploadedFile> */
    public array $documents = [];

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

    public function startEditing(): void
    {
        $this->authorize('edit', $this->evaluation);
        $this->evaluation->load(['answers.field', 'formVersion']);

        $this->editPoints = [];
        $this->editText = [];
        foreach ($this->evaluation->answers as $answer) {
            if ($answer->isScored() && $answer->max_points > 0) {
                $this->editPoints[$answer->typeform_field_id] = rtrim(rtrim(number_format((float) $answer->points, 2, '.', ''), '0'), '.');
            } elseif ($answer->field !== null && self::isCommentField($answer->field)) {
                $this->editText[$answer->typeform_field_id] = (string) $answer->value_text;
            }
        }
        $this->editing = true;
        $this->dispatch('open-all-sections');
    }

    public function cancelEditing(): void
    {
        $this->editing = false;
        $this->editPoints = [];
        $this->editText = [];
        $this->resetErrorBag();
    }

    public function saveEdits(Rescorer $rescorer, AuditLogger $audit): void
    {
        $this->authorize('edit', $this->evaluation);
        /** @var User $user */
        $user = auth()->user();
        $answers = $this->evaluation->answers()->get()->keyBy('typeform_field_id');

        $rules = [];
        foreach (array_keys($this->editPoints) as $fieldId) {
            $answer = $answers->get($fieldId);
            if ($answer === null || ! $answer->isScored()) {
                continue;
            }
            $rules["editPoints.$fieldId"] = ['required', 'numeric', 'min:0', 'max:'.(float) $answer->max_points];
        }
        foreach (array_keys($this->editText) as $fieldId) {
            $rules["editText.$fieldId"] = ['nullable', 'string', 'max:5000'];
        }
        $this->validate($rules, ['editPoints.*.max' => 'Points cannot exceed the maximum for this question.', 'editPoints.*.min' => 'Points cannot be negative.']);

        $edits = is_array($this->evaluation->edits) ? $this->evaluation->edits : [];
        $changes = [];
        $stamp = ['edited_by' => $user->id, 'edited_at' => now()->toIso8601String()];

        foreach ($this->editPoints as $fieldId => $value) {
            $answer = $answers->get($fieldId);
            if ($answer === null || ! $answer->isScored()) {
                continue;
            }
            $new = round((float) $value, 2);
            $original = isset($edits[$fieldId]['original_points']) ? (float) $edits[$fieldId]['original_points'] : (float) $answer->points;
            $current = isset($edits[$fieldId]['points']) ? (float) $edits[$fieldId]['points'] : (float) $answer->points;
            if ($new === $current) {
                continue;
            }
            $changes[$fieldId]['points'] = ['from' => $current, 'to' => $new];
            if ($new === $original) {
                unset($edits[$fieldId]['points'], $edits[$fieldId]['original_points']);
            } else {
                $edits[$fieldId] = ($edits[$fieldId] ?? []) + ['original_points' => $original];
                $edits[$fieldId]['points'] = $new;
                $edits[$fieldId] = array_merge($edits[$fieldId], $stamp);
            }
        }

        foreach ($this->editText as $fieldId => $value) {
            $answer = $answers->get($fieldId);
            if ($answer === null) {
                continue;
            }
            $new = trim((string) $value);
            $original = array_key_exists('original_text', $edits[$fieldId] ?? []) ? (string) $edits[$fieldId]['original_text'] : (string) $answer->value_text;
            $current = isset($edits[$fieldId]['text']) ? (string) $edits[$fieldId]['text'] : (string) $answer->value_text;
            if ($new === $current) {
                continue;
            }
            $changes[$fieldId]['text'] = ['from' => $current, 'to' => $new];
            if ($new === $original) {
                unset($edits[$fieldId]['text'], $edits[$fieldId]['original_text']);
            } else {
                $edits[$fieldId] = ($edits[$fieldId] ?? []) + ['original_text' => $original];
                $edits[$fieldId]['text'] = $new;
                $edits[$fieldId] = array_merge($edits[$fieldId], $stamp);
            }
        }

        // Drop entries that no longer override anything.
        $edits = array_filter($edits, fn (array $e) => array_key_exists('points', $e) || array_key_exists('text', $e));

        if ($changes !== []) {
            $this->evaluation->forceFill(['edits' => $edits === [] ? null : $edits])->save();
            $rescorer->rescoreSubmission($this->evaluation);
            $this->evaluation->refresh();
            $audit->log('submission.edited', $this->evaluation, ['fields' => $changes, 'overall_pct' => $this->evaluation->overall_pct], $this->evaluation->hospital_id, $user);
            session()->flash('status', 'Evaluation updated and re-scored.');
        }

        $this->cancelEditing();
    }

    public function uploadDocuments(AttachmentStore $store, AuditLogger $audit): void
    {
        $this->authorize('edit', $this->evaluation);
        $this->validate(['documents' => ['required', 'array', 'max:10'], 'documents.*' => ['file', 'max:10240']], ['documents.*.max' => 'Each file must be 10 MB or smaller.']);
        /** @var User $user */
        $user = auth()->user();

        foreach ($this->documents as $file) {
            $attachment = $store->upload($this->evaluation, $file, $user);
            $audit->log('submission.document_added', $this->evaluation, ['name' => $attachment->original_name, 'attachment_id' => $attachment->id], $this->evaluation->hospital_id, $user);
        }

        $count = count($this->documents);
        $this->documents = [];
        session()->flash('status', $count.' '.($count === 1 ? 'document' : 'documents').' added.');
    }

    public function deleteDocument(int $attachmentId, AttachmentStore $store, AuditLogger $audit): void
    {
        $this->authorize('edit', $this->evaluation);
        $attachment = SubmissionAttachment::query()->where('submission_id', $this->evaluation->id)->where('source', SubmissionAttachment::SOURCE_UPLOAD)->findOrFail($attachmentId);
        /** @var User $user */
        $user = auth()->user();

        $name = $attachment->original_name;
        $store->delete($attachment);
        $audit->log('submission.document_removed', $this->evaluation, ['name' => $name], $this->evaluation->hospital_id, $user);
        session()->flash('status', 'Document removed.');
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
            'edits' => is_array($this->evaluation->edits) ? $this->evaluation->edits : [],
            'supportingDocs' => $this->evaluation->attachments()->where('source', SubmissionAttachment::SOURCE_UPLOAD)->with('uploader')->get(),
            'rawJson' => $user->isEmotionStaff() ? json_encode($this->evaluation->raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'backUrl' => $user->isEmotionStaff() ? route('hospitals.evaluations.index', $this->evaluation->form->hospital) : route('evaluations.index'),
        ]);
    }
}
