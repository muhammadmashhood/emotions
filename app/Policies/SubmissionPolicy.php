<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        if ($user->isEmotionStaff()) {
            return true;
        }

        return $user->belongsToHospital($submission->hospital_id)
            && Submission::query()->whereKey($submission->id)->hospitalVisible()->exists();
    }

    public function review(User $user, Submission $submission): bool
    {
        return $user->isEmotionStaff();
    }

    public function rescore(User $user, Submission $submission): bool
    {
        return $user->isEmotionStaff();
    }

    /** Edit scores and comments, add or remove supporting documents. */
    public function edit(User $user, Submission $submission): bool
    {
        return $user->isEmotionStaff();
    }
}
