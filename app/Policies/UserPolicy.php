<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isHospitalAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isSuperAdmin() || ($user->isHospitalAdmin() && $user->hospital_id === $target->hospital_id);
    }

    /**
     * May $user invite someone with $role into $hospital?
     */
    public function invite(User $user, Role $role, ?Hospital $hospital): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isHospitalAdmin()
            && $hospital !== null
            && $user->belongsToHospital($hospital)
            && $role->isHospitalRole();
    }

    public function update(User $user, User $target): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isHospitalAdmin()
            && $target->hospital_id === $user->hospital_id
            && $target->role->isHospitalRole();
    }

    public function deactivate(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return false;
        }

        return $this->update($user, $target);
    }

    public function changeRole(User $user, User $target, Role $role): bool
    {
        if (! $this->update($user, $target)) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $role->isHospitalRole();
    }
}
