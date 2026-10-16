<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;

class SubmissionPolicy
{
    public function view(User $user, Submission $submission): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->belongsToHospital($submission->hospital_id)
            && Submission::query()->whereKey($submission->id)->hospitalVisible()->exists();
    }

    public function review(User $user, Submission $submission): bool
    {
        return $user->isSuperAdmin();
    }

    public function rescore(User $user, Submission $submission): bool
    {
        return $user->isSuperAdmin();
    }
}
