<?php

namespace App\Livewire\Admin\Users;

use App\Domain\Users\InvitationMailFailed;
use App\Domain\Users\InvitationService;
use App\Domain\Users\UserManager;
use App\Enums\Role;
use App\Models\Hospital;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $role = '';

    #[Url]
    public string $hospital = '';

    public string $inviteEmail = '';

    public string $inviteRole = 'hospital_viewer';

    public string $inviteHospital = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'hospital'], true)) {
            $this->resetPage();
        }
    }

    public function invite(InvitationService $invitations): void
    {
        $this->validate([
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', Rule::enum(Role::class)],
            'inviteHospital' => ['nullable', 'exists:hospitals,uuid'],
        ]);

        $role = Role::from($this->inviteRole);
        $hospital = $this->inviteHospital !== '' ? Hospital::query()->where('uuid', $this->inviteHospital)->first() : null;

        $this->authorize('invite', [User::class, $role, $hospital]);

        /** @var User $actor */
        $actor = auth()->user();
        try {
            $invitations->invite($this->inviteEmail, $role, $hospital, $actor);
        } catch (InvitationMailFailed $e) {
            $this->addError('inviteEmail', $e->getMessage());

            return;
        }

        $this->reset('inviteEmail', 'inviteHospital');
        session()->flash('status', 'Invitation sent.');
        $this->dispatch('invite-sent');
    }

    public function toggleActive(int $userId, UserManager $users): void
    {
        $user = User::query()->findOrFail($userId);
        /** @var User $actor */
        $actor = auth()->user();

        if ($user->is_active) {
            $this->authorize('deactivate', $user);
            $users->deactivate($user, $actor);
        } else {
            $this->authorize('update', $user);
            $users->reactivate($user, $actor);
        }
    }

    public function changeRole(int $userId, string $role, UserManager $users): void
    {
        $user = User::query()->findOrFail($userId);
        $newRole = Role::from($role);
        $this->authorize('changeRole', [$user, $newRole]);

        // Role changes across the hospital boundary are not allowed here.
        if ($newRole === Role::SuperAdmin || $user->isSuperAdmin()) {
            abort(403, 'Super admin roles are managed by invitation only.');
        }

        /** @var User $actor */
        $actor = auth()->user();
        $users->changeRole($user, $newRole, $actor);
    }

    public function resend(int $invitationId, InvitationService $invitations): void
    {
        $invitation = UserInvitation::query()->findOrFail($invitationId);
        $this->authorize('invite', [User::class, $invitation->role, $invitation->hospital]);
        /** @var User $actor */
        $actor = auth()->user();
        try {
            $invitations->resend($invitation, $actor);
        } catch (InvitationMailFailed $e) {
            $this->addError('inviteEmail', $e->getMessage());

            return;
        }
        session()->flash('status', 'Invitation re-sent.');
    }

    public function render(): View
    {
        $users = User::query()
            ->with('hospital')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
            ->when($this->role !== '', fn ($q) => $q->where('role', $this->role))
            ->when($this->hospital !== '', fn ($q) => $q->whereHas('hospital', fn ($h) => $h->where('uuid', $this->hospital)))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.admin.users.index', [
            'users' => $users,
            'hospitals' => Hospital::query()->orderBy('name')->get(),
            // Pending invitations are listed above the users on the first page, with the same filters.
            'invitations' => $users->onFirstPage()
                ? UserInvitation::query()->with('hospital')->whereNull('accepted_at')
                    ->when($this->search !== '', fn ($q) => $q->where('email', 'like', "%{$this->search}%"))
                    ->when($this->role !== '', fn ($q) => $q->where('role', $this->role))
                    ->when($this->hospital !== '', fn ($q) => $q->whereHas('hospital', fn ($h) => $h->where('uuid', $this->hospital)))
                    ->orderByDesc('created_at')->limit(50)->get()
                : collect(),
            'roles' => Role::cases(),
        ]);
    }
}
