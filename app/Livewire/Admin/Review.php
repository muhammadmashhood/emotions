<?php

namespace App\Livewire\Admin;

use App\Domain\Audit\AuditLogger;
use App\Enums\SubmissionStatus;
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
            ->with(['form:id,title,uuid', 'hospital:id,name,uuid'])
            ->where('status', SubmissionStatus::PendingReview->value)
            ->when($this->hospital !== '', fn ($q) => $q->whereHas('hospital', fn ($h) => $h->where('uuid', $this->hospital)))
            ->orderBy('submitted_at')
            ->paginate(25);

        return view('livewire.admin.review.index', [
            'pending' => $pending,
            'hospitals' => Hospital::query()->orderBy('name')->get(['id', 'uuid', 'name', 'require_review']),
        ]);
    }
}
