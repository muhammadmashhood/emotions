<?php

namespace App\Livewire\Admin;

use App\Domain\Audit\AuditLogger;
use App\Enums\InfoKey;
use App\Enums\SubmissionStatus;
use App\Models\FormField;
use App\Models\Hospital;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Review queue (spec M7): evaluations waiting for approval in hospitals with `require_review`.
 */
#[Layout('layouts.app')]
class Review extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $hospital = '';

    /** @var array<int, string> */
    public array $notes = [];

    public function updatedHospital(): void
    {
        $this->resetPage();
    }

    public function approve(int $id, AuditLogger $audit): void
    {
        $this->decide($id, SubmissionStatus::Approved, 'submission.approved', $audit);
    }

    public function reject(int $id, AuditLogger $audit): void
    {
        $this->decide($id, SubmissionStatus::Rejected, 'submission.rejected', $audit);
    }

    private function decide(int $id, SubmissionStatus $status, string $action, AuditLogger $audit): void
    {
        $submission = Submission::query()->findOrFail($id);
        $this->authorize('review', $submission);
        /** @var User $user */
        $user = auth()->user();
        $note = trim($this->notes[$id] ?? '');

        $submission->forceFill([
            'status' => $status,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $note !== '' ? $note : null,
        ])->save();

        $audit->log($action, $submission, ['note' => $submission->review_note], $submission->hospital_id, $user);
        unset($this->notes[$id]);
        session()->flash('status', $status === SubmissionStatus::Approved ? 'Evaluation approved.' : 'Evaluation rejected.');
    }

    public function render(): View
    {
        $pending = Submission::query()
            ->with(['form:id,title,uuid', 'hospital:id,name,uuid', 'branch:id,name'])
            ->where('status', SubmissionStatus::PendingReview->value)
            ->when($this->hospital !== '', fn ($q) => $q->whereHas('hospital', fn ($h) => $h->where('uuid', $this->hospital)))
            ->orderBy('submitted_at')
            ->paginate(25);

        // Flag blank info fields only where the form actually maps that info key.
        $mapped = FormField::query()
            ->whereIn('form_version_id', $pending->getCollection()->pluck('form_version_id')->unique())
            ->whereIn('info_key', [InfoKey::Facility->value, InfoKey::VisitDate->value, InfoKey::Doctor->value])
            ->get(['form_version_id', 'info_key'])
            ->groupBy('form_version_id')
            ->map(fn ($fields) => $fields->pluck('info_key')->all());

        $flags = $pending->getCollection()->mapWithKeys(fn (Submission $e) => [$e->id => $this->flags($e, $mapped[$e->form_version_id] ?? [])]);

        return view('livewire.admin.review.index', [
            'pending' => $pending,
            'flags' => $flags,
            'hospitals' => Hospital::query()->orderBy('name')->get(['id', 'uuid', 'name', 'require_review']),
        ]);
    }

    /**
     * Reasons to look twice before approving.
     *
     * @param  list<string>  $mappedKeys
     * @return list<string>
     */
    private function flags(Submission $submission, array $mappedKeys): array
    {
        $flags = [];
        if ($submission->overall_pct !== null && $submission->overall_pct < 60) {
            $flags[] = 'Low score';
        }
        if ($submission->is_partial) {
            $flags[] = 'Partial response';
        }
        $labels = [InfoKey::Facility->value => 'Facility missing', InfoKey::VisitDate->value => 'Missing visit date', InfoKey::Doctor->value => 'Doctor name blank'];
        foreach ($labels as $key => $label) {
            if (in_array($key, $mappedKeys, true) && trim((string) $submission->infoValue($key)) === '') {
                $flags[] = $label;
            }
        }

        return $flags;
    }
}
