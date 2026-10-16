<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

class FormPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Form $form): bool
    {
        return $user->isSuperAdmin() || $user->belongsToHospital($form->hospital_id);
    }

    public function manage(User $user, Form $form): bool
    {
        return $user->isSuperAdmin();
    }
}
