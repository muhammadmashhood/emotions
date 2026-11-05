<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="flex flex-col gap-[22px]">
    <div>
        <h1 class="text-2xl font-semibold tracking-[-0.015em]">Sign in</h1>
        <p class="text-[#D9D3D9] mt-1">Use the email your hospital invited you with.</p>
    </div>

    <x-auth-session-status :status="session('status')" />

    <form wire:submit="login" class="flex flex-col gap-[22px]">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="form.email" id="email" type="email" name="email" placeholder="name@hospital.ae" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('form.email')" class="mt-2 !text-[#FFB4AC]" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" :value="__('Password')" />
                @if (Route::has('password.request'))
                    <a class="auth-link text-xs mb-1.5" href="{{ route('password.request') }}" wire:navigate>{{ __('Forgot password?') }}</a>
                @endif
            </div>
            <x-text-input wire:model="form.password" id="password" type="password" name="password" placeholder="••••••••" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('form.password')" class="mt-2 !text-[#FFB4AC]" />
        </div>

        <label for="remember" class="inline-flex items-center gap-2 text-[12.5px] text-[#D9D3D9]">
            <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-white/30 bg-white/10 text-[#A8A4F7] focus:ring-[#A8A4F7]" name="remember">
            {{ __('Keep me signed in') }}
        </label>

        <x-primary-button>{{ __('Sign in') }}</x-primary-button>
    </form>

    <p class="text-xs text-[#C5BFC2] text-center">Need access? Ask your hospital's PX lead or <a href="mailto:info@e-motion.ae" class="text-white underline underline-offset-2">info@e-motion.ae</a></p>
</div>
