<?php

namespace App\Livewire\Auth;

use App\Livewire\Actions\Logout;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * First sign-in for accounts created with a temporary password: the user picks their own before continuing.
 */
#[Layout('layouts.guest')]
class SetPassword extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (! $this->user()->must_change_password) {
            $this->redirect(route('home'), navigate: false);
        }
    }

    public function save(): void
    {
        $this->validate([
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $this->user()->forceFill([
            'password' => $this->password,
            'must_change_password' => false,
        ])->save();

        Session::regenerate();
        $this->reset('password', 'password_confirmation');

        $this->redirect(route('home'), navigate: false);
    }

    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect(route('login'), navigate: false);
    }

    public function render(): View
    {
        return view('livewire.auth.set-password');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
