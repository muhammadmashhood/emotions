<?php

namespace App\Domain\Users;

use App\Domain\Audit\AuditLogger;
use App\Enums\Role;
use App\Models\User;

class UserManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function deactivate(User $user, User $by): void
    {
        $user->forceFill(['is_active' => false])->save();
        $this->audit->log('user.deactivated', $user, [], $user->hospital_id, $by);
    }

    public function reactivate(User $user, User $by): void
    {
        $user->forceFill(['is_active' => true])->save();
        $this->audit->log('user.reactivated', $user, [], $user->hospital_id, $by);
    }

    public function changeRole(User $user, Role $role, User $by): void
    {
        $old = $user->role->value;
        $user->forceFill(['role' => $role])->save();
        $this->audit->log('user.role_changed', $user, ['from' => $old, 'to' => $role->value], $user->hospital_id, $by);
    }
}
