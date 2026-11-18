<?php

namespace App\Domain\Users;

use App\Domain\Audit\AuditLogger;
use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserManager
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create an account directly with a temporary password (for when invitation emails do not arrive).
     * Same rules as InvitationService::invite. The user must set their own password at first sign-in.
     * The password is never logged or audited. Authorisation is checked by the caller through UserPolicy.
     */
    public function create(string $name, string $email, Role $role, ?Hospital $hospital, string $password, User $by): User
    {
        $email = Str::lower(trim($email));

        Validator::make(['password' => $password], ['password' => ['required', 'string', Password::defaults()]])->validate();

        if (! $role->isHospitalRole() && $hospital !== null) {
            throw ValidationException::withMessages(['role' => 'eMotion staff do not belong to a hospital.']);
        }
        if ($role->isHospitalRole() && $hospital === null) {
            throw ValidationException::withMessages(['hospital_id' => 'A hospital is required for this role.']);
        }
        if (User::query()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'A user with this email already exists.']);
        }

        return DB::transaction(function () use ($name, $email, $role, $hospital, $password, $by): User {
            // A direct account supersedes any pending invitation for the same address.
            UserInvitation::query()->where('email', $email)->whereNull('accepted_at')->delete();

            $user = User::query()->create([
                'name' => trim($name),
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'hospital_id' => $hospital?->id,
                'is_active' => true,
                'must_change_password' => true,
            ]);

            $this->audit->log('user.created', $user, ['email' => $email, 'role' => $role->value], $hospital, $by);

            return $user;
        });
    }

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
