<?php

namespace App\Livewire\Shared;

use App\Domain\Users\InvitationService;
use App\Domain\Users\UserManager;
use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Users + invitations for one hospital. Used by the admin hospital page and the hospital_admin "Team" page.
 */
class UserManagement extends Component
{
    public Hospital $hospital;

    public string $inviteEmail = '';

    public string $inviteRole = 'hospital_viewer';

    public function mount(Hospital $hospital): void
    {
        $this->authorize('manageUsers', $hospital);
        $this->hospital = $hospital;
    }

    public function invite(InvitationService $invitations): void
    {
        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', Rule::in(array_map(fn (Role $r) => $r->value, Role::hospitalRoles()))],
        ]);

        $role = Role::from($this->inviteRole);
        $this->authorize('invite', [User::class, $role, $this->hospital]);

        $invitations->invite($this->inviteEmail, $role, $this->hospital, $this->currentUser());

        $this->reset('inviteEmail');
        $this->inviteRole = 'hospital_viewer';
        session()->flash('status', 'Invitation sent.');
    }

    public function resend(int $invitationId, InvitationService $invitations): void
    {
        $invitation = UserInvitation::query()->where('hospital_id', $this->hospital->id)->findOrFail($invitationId);
        $this->authorize('invite', [User::class, $invitation->role, $this->hospital]);

        $invitations->resend($invitation, $this->currentUser());
        session()->flash('status', 'Invitation re-sent.');
    }

    public function cancelInvitation(int $invitationId): void
    {
        $invitation = UserInvitation::query()->where('hospital_id', $this->hospital->id)->findOrFail($invitationId);
        $this->authorize('invite', [User::class, $invitation->role, $this->hospital]);
        $invitation->delete();
    }

    public function deactivate(int $userId, UserManager $users): void
    {
        $user = $this->hospitalUser($userId);
        $this->authorize('deactivate', $user);
        $users->deactivate($user, $this->currentUser());
    }

    public function reactivate(int $userId, UserManager $users): void
    {
        $user = $this->hospitalUser($userId);
        $this->authorize('update', $user);
        $users->reactivate($user, $this->currentUser());
    }

    public function changeRole(int $userId, string $role, UserManager $users): void
    {
        $user = $this->hospitalUser($userId);
        $newRole = Role::from($role);
        if (! $newRole->isHospitalRole()) {
            abort(403);
        }
        $this->authorize('changeRole', [$user, $newRole]);
        $users->changeRole($user, $newRole, $this->currentUser());
    }

    private function hospitalUser(int $id): User
    {
        return User::query()->where('hospital_id', $this->hospital->id)->findOrFail($id);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        return view('livewire.shared.user-management', [
            'users' => User::query()->where('hospital_id', $this->hospital->id)->orderBy('name')->get(),
            'invitations' => UserInvitation::query()->where('hospital_id', $this->hospital->id)->whereNull('accepted_at')->orderByDesc('created_at')->get(),
            'roles' => Role::hospitalRoles(),
        ]);
    }
}
