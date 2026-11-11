<?php

namespace App\Jobs;

use App\Domain\Submissions\AttachmentStore;
use App\Models\Submission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Copies a submission's Typeform file answers to our disk. Best effort: a failure never fails the delivery.
 */
class StoreSubmissionAttachments implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $submissionId) {}

    public function handle(AttachmentStore $store): void
    {
        $submission = Submission::query()->find($this->submissionId);
        if ($submission === null) {
            return;
        }

        try {
            $store->syncFromTypeform($submission);
        } catch (Throwable $e) {
            Log::warning('attachment.sync_failed', ['submission_id' => $this->submissionId, 'error' => $e->getMessage()]);
        }
    }
}
