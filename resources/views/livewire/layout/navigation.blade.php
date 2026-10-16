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
    $icons = [
        'overview' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
        'hospitals' => 'M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6M12 9v4m-2-2h4',
        'users' => 'M16 11a4 4 0 1 0-8 0 4 4 0 0 0 8 0zM4 21a8 8 0 0 1 16 0',
        'deliveries' => 'M4 7l8-4 8 4v10l-8 4-8-4V7zm8 4v10M4 7l8 4 8-4',
        'review' => 'M9 12l2 2 4-4m6 2a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
        'audit' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2m-6 7h6m-6 4h4',
        'settings' => 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm7.4-3a7.4 7.4 0 0 0-.1-1l2-1.6-2-3.4-2.4 1a7.6 7.6 0 0 0-1.7-1L14.8 3H9.2l-.4 2.5a7.6 7.6 0 0 0-1.7 1l-2.4-1-2 3.4L4.7 11a7.4 7.4 0 0 0 0 2l-2 1.6 2 3.4 2.4-1c.5.4 1.1.7 1.7 1l.4 2.5h5.6l.4-2.5c.6-.3 1.2-.6 1.7-1l2.4 1 2-3.4-2-1.6c.1-.3.1-.7.1-1z',
        'dashboard' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
        'evaluations' => 'M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2z',
        'team' => 'M17 20h5v-2a3 3 0 0 0-5.4-1.8M17 20H7m10 0v-2c0-.7-.1-1.3-.4-1.8M7 20H2v-2a3 3 0 0 1 5.4-1.8M7 20v-2c0-.7.1-1.3.4-1.8m0 0a5 5 0 0 1 9.2 0M15 7a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
    ];
    $links = $user->isSuperAdmin()
        ? [
            ['route' => 'admin.overview', 'label' => 'Overview', 'active' => 'admin.overview', 'icon' => 'overview'],
            ['route' => 'admin.hospitals.index', 'label' => 'Hospitals', 'active' => 'admin.hospitals.*', 'icon' => 'hospitals'],
            ['route' => 'admin.users.index', 'label' => 'Users', 'active' => 'admin.users.*', 'icon' => 'users'],
            ['route' => 'admin.review', 'label' => 'Review queue', 'active' => 'admin.review', 'icon' => 'review'],
            ['route' => 'admin.deliveries', 'label' => 'Deliveries', 'active' => 'admin.deliveries', 'icon' => 'deliveries'],
            ['route' => 'admin.audit', 'label' => 'Audit log', 'active' => 'admin.audit', 'icon' => 'audit'],
            ['route' => 'admin.settings.typeform', 'label' => 'Settings', 'active' => 'admin.settings.*', 'icon' => 'settings'],
        ]
        : array_values(array_filter([
            ['route' => 'dashboard', 'label' => 'Dashboard', 'active' => 'dashboard', 'icon' => 'dashboard'],
            ['route' => 'evaluations.index', 'label' => 'Evaluations', 'active' => 'evaluations.*', 'icon' => 'evaluations'],
            $user->isHospitalAdmin() ? ['route' => 'team', 'label' => 'Team', 'active' => 'team', 'icon' => 'team'] : null,
        ]));
    $links = array_values(array_filter($links, fn ($l) => \Illuminate\Support\Facades\Route::has($l['route'])));
@endphp

<div>
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-night/60 lg:hidden" @click="sidebarOpen = false" x-cloak></div>

    <aside class="fixed inset-y-0 left-0 z-50 w-64 flex flex-col bg-gradient-to-b from-night to-night-deep text-white transition-transform lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen no-print"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" aria-label="Main navigation">
        <div class="px-5 pt-6 pb-4 flex items-center gap-3">
            @if($hospital?->logo_path)
                <img src="{{ Storage::disk('public')->url($hospital->logo_path) }}" alt="" class="h-10 w-10 rounded-xl object-contain bg-white p-1">
            @else
                <span class="h-10 w-10 rounded-xl bg-white text-night font-bold text-xl flex items-center justify-center shadow-glow" aria-hidden="true">e</span>
            @endif
            <div class="min-w-0">
                <div class="font-semibold tracking-tight truncate">{{ $hospital?->name ?? 'eMotion' }}</div>
                <div class="text-[11px] uppercase tracking-[0.18em] text-white/50">{{ $hospital ? 'Evaluations' : 'Mystery shopping' }}</div>
            </div>
            <button type="button" class="ml-auto lg:hidden p-1 text-white/60 hover:text-white" @click="sidebarOpen = false" aria-label="Close menu">✕</button>
        </div>

        <nav class="px-3 mt-2 space-y-1 flex-1">
            <div class="eyebrow text-white/40 px-3 pb-2">Menu</div>
            @foreach($links as $link)
                <a href="{{ route($link['route']) }}" wire:navigate class="nav-item {{ request()->routeIs($link['active']) ? 'nav-item-active' : '' }}" @if(request()->routeIs($link['active'])) aria-current="page" @endif>
                    <svg class="h-5 w-5 shrink-0 opacity-90" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="{{ $icons[$link['icon']] }}"/></svg>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="px-3 pb-5 pt-3 border-t border-white/10">
            <a href="{{ route('profile') }}" wire:navigate class="nav-item {{ request()->routeIs('profile') ? 'nav-item-active' : '' }}">
                <span class="h-8 w-8 rounded-full bg-white/15 flex items-center justify-center text-sm font-semibold shrink-0" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                <span class="min-w-0">
                    <span class="block truncate text-white" x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></span>
                    <span class="block text-[11px] text-white/50 truncate">{{ $user->role->label() }}</span>
                </span>
            </a>
            <button type="button" wire:click="logout" class="nav-item w-full text-left mt-1">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1"/></svg>
                <span>Log out</span>
            </button>
        </div>
    </aside>
</div>
