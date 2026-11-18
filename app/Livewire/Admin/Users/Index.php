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
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    /** 'invite' emails a link; 'create' sets a temporary password the admin hands over themselves. */
    public string $mode = 'invite';

    public string $createName = '';

    public string $createPassword = '';

    /** Shown once after a direct creation, then cleared. Never persisted or emailed. */
    public ?string $createdPassword = null;

    public ?string $createdEmail = null;

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

    public function generatePassword(): void
    {
        // Letters and digits only so it can be read out over the phone.
        $this->createPassword = Str::password(12, symbols: false);
    }

    public function create(UserManager $users): void
    {
        $this->validate([
            'createName' => ['required', 'string', 'max:255'],
            'inviteEmail' => ['required', 'email', 'max:255'],
            'inviteRole' => ['required', Rule::enum(Role::class)],
            'inviteHospital' => ['nullable', 'exists:hospitals,uuid'],
            'createPassword' => ['required', 'string'],
        ]);

        $role = Role::from($this->inviteRole);
        $hospital = $this->inviteHospital !== '' ? Hospital::query()->where('uuid', $this->inviteHospital)->first() : null;

        $this->authorize('create', [User::class, $role, $hospital]);

        /** @var User $actor */
        $actor = auth()->user();
        try {
            $user = $users->create($this->createName, $this->inviteEmail, $role, $hospital, $this->createPassword, $actor);
        } catch (ValidationException $e) {
            $map = ['email' => 'inviteEmail', 'role' => 'inviteRole', 'hospital_id' => 'inviteHospital', 'password' => 'createPassword'];
            foreach ($e->errors() as $field => $messages) {
                $this->addError($map[$field] ?? $field, (string) ($messages[0] ?? 'Invalid value.'));
            }

            return;
        }

        $this->createdPassword = $this->createPassword;
        $this->createdEmail = $user->email;
        $this->reset('createName', 'createPassword', 'inviteEmail', 'inviteHospital');
        session()->flash('status', 'User created. Share the temporary password below with them.');
        $this->dispatch('invite-sent');
    }

    public function dismissCreated(): void
    {
        $this->reset('createdPassword', 'createdEmail', 'createName', 'createPassword');
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
        if (! $newRole->isHospitalRole() || $user->isEmotionStaff()) {
            abort(403, 'eMotion staff roles are managed by invitation only.');
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
            session()->flash('error', $e->getMessage());

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
