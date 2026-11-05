@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $hospital = $user->hospital;
    $facilities = $hospital?->branches()->where('is_active', true)->count() ?? 0;
    $surveys = $hospital?->forms()->active()->count() ?? 0;

    $links = array_values(array_filter([
        ['route' => 'dashboard', 'label' => 'Dashboard', 'active' => ['dashboard']],
        ['route' => 'evaluations.index', 'label' => 'Evaluations', 'active' => ['evaluations.*']],
        $user->isHospitalAdmin() ? ['route' => 'team', 'label' => 'Team', 'active' => ['team']] : null,
    ]));
@endphp

<div>
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-em-ink/40 lg:hidden" @click="sidebarOpen = false" x-cloak></div>

    <aside class="fixed inset-y-0 left-0 z-50 w-[232px] flex flex-col gap-7 px-3.5 pt-6 pb-5 bg-em-side border-r border-em-line text-em-ink text-[13px] transition-transform lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen overflow-y-auto no-print"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" aria-label="Main navigation">
        <div class="flex items-center gap-[9px] px-2">
            <span class="h-8 w-8 rounded-[9px] bg-em-ink text-white flex items-center justify-center text-[21px] font-medium leading-none pb-[3px]" aria-hidden="true">e</span>
            <span class="text-[17px] font-light tracking-[0.09em]">MOTION</span>
            <button type="button" class="ml-auto lg:hidden p-1 text-em-mute hover:text-em-ink" @click="sidebarOpen = false" aria-label="Close menu">✕</button>
        </div>

        @if($hospital)
            <div class="p-3 border border-em-line rounded-xl bg-white flex items-center gap-2.5">
                @if($hospital->logo_path)
                    <img src="{{ Storage::disk('public')->url($hospital->logo_path) }}" alt="" class="h-9 w-9 rounded-lg object-contain border border-em-line p-0.5 shrink-0">
                @endif
                <div class="min-w-0 flex flex-col gap-0.5">
                    <span class="eyebrow">Hospital</span>
                    <span class="font-medium truncate">{{ $hospital->name }}</span>
                    <span class="text-[11.5px] text-em-mute">{{ $facilities }} {{ Str::plural('facility', $facilities) }} · {{ $surveys }} {{ Str::plural('survey', $surveys) }}</span>
                </div>
            </div>
        @endif

        <nav class="flex flex-col gap-1">
            <div class="eyebrow px-2.5 pb-1.5">Menu</div>
            @foreach($links as $link)
                @php $active = request()->routeIs(...$link['active']); @endphp
                <a href="{{ route($link['route']) }}" wire:navigate class="em-nav-item {{ $active ? 'em-nav-item-active' : '' }}" @if($active) aria-current="page" @endif>
                    <span class="h-1.5 w-1.5 rounded-full shrink-0 {{ $active ? 'bg-em-accent' : 'bg-[#D9D3D9]' }}" aria-hidden="true"></span>
                    <span>{{ $link['label'] }}</span>
                </a>
            @endforeach
        </nav>

        <div class="mt-auto flex items-center gap-2.5 px-2 pt-3 border-t border-em-line">
            <a href="{{ route('profile') }}" wire:navigate class="h-8 w-8 rounded-full bg-em-accent-soft text-em-accent flex items-center justify-center font-semibold text-xs shrink-0" aria-label="Your profile">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</a>
            <div class="flex-1 min-w-0">
                <a href="{{ route('profile') }}" wire:navigate class="block font-medium truncate hover:text-em-accent" x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name">{{ $user->name }}</a>
                <div class="text-[11.5px] text-em-mute truncate">{{ $user->role->label() }}</div>
            </div>
            <button type="button" wire:click="logout" class="border border-em-line bg-white rounded-lg px-2 py-[5px] text-[11.5px] text-em-soft hover:text-em-ink hover:border-[#D9D3D9]">Log out</button>
        </div>
    </aside>
</div>
