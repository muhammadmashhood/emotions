<?php

namespace App\Policies;

use App\Models\Hospital;
use App\Models\User;

class HospitalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function view(User $user, Hospital $hospital): bool
    {
        return $user->isSuperAdmin() || $user->belongsToHospital($hospital);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function update(User $user, Hospital $hospital): bool
    {
        return $user->isSuperAdmin();
    }

    public function manageUsers(User $user, Hospital $hospital): bool
    {
        return $user->isSuperAdmin() || ($user->isHospitalAdmin() && $user->belongsToHospital($hospital));
    }
}
