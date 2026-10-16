<?php

use App\Livewire\Actions\Logout;
use Livewire\Volt\Component;

new class extends Component
{
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $hospital = $user->isSuperAdmin() ? null : $user->hospital;
    $links = $user->isSuperAdmin()
        ? [
            ['route' => 'admin.overview', 'label' => 'Overview', 'active' => 'admin.overview'],
            ['route' => 'admin.hospitals.index', 'label' => 'Hospitals', 'active' => 'admin.hospitals.*'],
            ['route' => 'admin.users.index', 'label' => 'Users', 'active' => 'admin.users.*'],
            ['route' => 'admin.deliveries', 'label' => 'Deliveries', 'active' => 'admin.deliveries'],
            ['route' => 'admin.review', 'label' => 'Review', 'active' => 'admin.review'],
            ['route' => 'admin.audit', 'label' => 'Audit log', 'active' => 'admin.audit'],
            ['route' => 'admin.settings.typeform', 'label' => 'Settings', 'active' => 'admin.settings.*'],
        ]
        : array_values(array_filter([
            ['route' => 'dashboard', 'label' => 'Dashboard', 'active' => 'dashboard'],
            ['route' => 'evaluations.index', 'label' => 'Evaluations', 'active' => 'evaluations.*'],
            $user->isHospitalAdmin() ? ['route' => 'team', 'label' => 'Team', 'active' => 'team'] : null,
        ]));
    $links = array_values(array_filter($links, fn ($l) => \Illuminate\Support\Facades\Route::has($l['route'])));
@endphp

<nav x-data="{ open: false }" class="bg-white border-b border-line no-print">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center gap-6">
                <a href="{{ $user->isSuperAdmin() ? route('admin.overview') : route('dashboard') }}" wire:navigate class="flex items-center gap-2">
                    @if($hospital?->logo_path)
                        <img src="{{ Storage::disk('public')->url($hospital->logo_path) }}" alt="" class="h-9 w-9 rounded object-contain bg-white">
                    @else
                        <x-application-logo class="h-9 w-9" />
                    @endif
                    <span class="font-semibold text-ink tracking-tight">{{ $hospital?->name ?? config('app.name') }}</span>
                </a>

                <div class="hidden sm:flex sm:items-center gap-1">
                    @foreach($links as $link)
                        <x-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'])" wire:navigate>{{ $link['label'] }}</x-nav-link>
                    @endforeach
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 text-sm font-medium rounded-md text-ink-soft bg-white hover:text-ink transition">
                            <div x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                            <svg class="fill-current h-4 w-4 ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <div class="px-4 py-2 text-xs text-ink-soft">{{ $user->role->label() }}</div>
                        <x-dropdown-link :href="route('profile')" wire:navigate>Profile</x-dropdown-link>
                        <button wire:click="logout" class="w-full text-start">
                            <x-dropdown-link>Log out</x-dropdown-link>
                        </button>
                    </x-slot>
                </x-dropdown>
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-ink-soft hover:text-ink hover:bg-paper" aria-label="Toggle menu">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            @foreach($links as $link)
                <x-responsive-nav-link :href="route($link['route'])" :active="request()->routeIs($link['active'])" wire:navigate>{{ $link['label'] }}</x-responsive-nav-link>
            @endforeach
        </div>
        <div class="pt-4 pb-1 border-t border-line">
            <div class="px-4">
                <div class="font-medium text-base text-ink">{{ $user->name }}</div>
                <div class="font-medium text-sm text-ink-soft">{{ $user->email }}</div>
            </div>
            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>Profile</x-responsive-nav-link>
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>Log out</x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
