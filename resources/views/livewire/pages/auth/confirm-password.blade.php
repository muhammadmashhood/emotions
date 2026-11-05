<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-[22px]">
    <div>
        <h1 class="text-2xl font-semibold tracking-[-0.015em]">Confirm your password</h1>
        <p class="text-[#D9D3D9] mt-1">{{ __('This is a secure area of the application. Please confirm your password before continuing.') }}</p>
    </div>

    <form wire:submit="confirmPassword" class="flex flex-col gap-[22px]">
        <div>
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input wire:model="password" id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2 !text-[#FFB4AC]" />
        </div>

        <x-primary-button>{{ __('Confirm') }}</x-primary-button>
    </form>
</div>
