<?php

namespace App\Domain\Submissions;

use App\Enums\Role;
use App\Mail\EvaluationPublishedMail;
use App\Mail\EvaluationSubmittedMail;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Email notifications about evaluations. One queued email per recipient; a mail failure is logged and never
 * propagates, because the evaluation itself must not depend on the mail server.
 *
 * Callers decide *when* to notify (live webhook arrivals, Approve actions). This class decides *who*.
 */
class EvaluationNotifier
{
    /** A complete evaluation has arrived through the live webhook: tell eMotion staff. */
    public function submitted(Submission $submission): void
    {
        $submission->loadMissing(['hospital', 'form', 'branch']);

        $recipients = User::query()
            ->whereIn('role', array_map(fn (Role $r) => $r->value, Role::staffRoles()))
            ->where('is_active', true)
            ->get();

        $this->send($recipients, fn () => new EvaluationSubmittedMail($submission), 'submitted', $submission);
    }

    /** The evaluation is approved and visible on the hospital dashboard: tell that hospital's admins. */
    public function published(Submission $submission): void
    {
        $submission->loadMissing(['hospital', 'form', 'branch']);

        $recipients = User::query()
            ->where('role', Role::HospitalAdmin->value)
            ->where('hospital_id', $submission->hospital_id)
            ->where('is_active', true)
            ->whereHas('hospital', fn ($q) => $q->where('is_active', true))
            ->get();

        $this->send($recipients, fn () => new EvaluationPublishedMail($submission), 'published', $submission);
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @param  callable(): Mailable  $mailable
     */
    private function send(Collection $recipients, callable $mailable, string $kind, Submission $submission): void
    {
        foreach ($recipients as $user) {
            try {
                Mail::to($user->email, $user->name)->queue($mailable());
            } catch (Throwable $e) {
                Log::warning('evaluation.notify_failed', [
                    'kind' => $kind,
                    'submission_id' => $submission->id,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
