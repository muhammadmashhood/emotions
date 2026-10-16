<?php

namespace App\Livewire\Auth;

use App\Domain\Users\InvitationService;
use App\Models\UserInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.guest')]
class AcceptInvitation extends Component
{
    #[Locked]
    public string $token = '';

    public ?UserInvitation $invitation = null;

    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token, InvitationService $invitations): void
    {
        $this->token = $token;
        $invitation = $invitations->findByToken($token);

        if ($invitation !== null && ! $invitation->isAccepted() && ! $invitation->isExpired()) {
            $this->invitation = $invitation->load('hospital');
        }
    }

    public function accept(InvitationService $invitations): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = $invitations->accept($this->token, $this->name, $this->password);

        Auth::login($user);
        session()->regenerate();

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.accept-invitation');
    }
}
