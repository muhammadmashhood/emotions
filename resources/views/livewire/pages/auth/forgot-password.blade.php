<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div class="flex flex-col gap-[22px]">
    <div>
        <h1 class="text-2xl font-semibold tracking-[-0.015em]">Reset your password</h1>
        <p class="text-[#D9D3D9] mt-1">{{ __('Enter your email and we will send you a link to choose a new password.') }}</p>
    </div>

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-[22px]">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" type="email" name="email" placeholder="name@hospital.ae" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2 !text-[#FFB4AC]" />
        </div>

        <x-primary-button>{{ __('Email Password Reset Link') }}</x-primary-button>
    </form>

    <a href="{{ route('login') }}" wire:navigate class="auth-link text-xs text-center">← Back to sign in</a>
</div>
