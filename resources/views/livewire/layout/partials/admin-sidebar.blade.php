@php
    /** @var \App\Models\User $user */
    $user = auth()->user();
    $route = request()->route();

    // The hospital the current page is about, if any (drives the switcher and "Hospital view").
    // Parameters may arrive bound (models) or raw (uuids), depending on how the page was resolved.
    $param = function (string $key, string $class) use ($route) {
        $value = $route?->parameter($key);

        return $value instanceof $class ? $value : (is_string($value) ? $class::query()->where('uuid', $value)->first() : null);
    };
    $current = $param('hospital', \App\Models\Hospital::class)
        ?? $param('form', \App\Models\Form::class)?->hospital
        ?? $param('evaluation', \App\Models\Submission::class)?->hospital;

    $hospitals = \App\Models\Hospital::query()->orderBy('name')->get(['id', 'uuid', 'name', 'is_active']);
    $pendingReview = \App\Models\Submission::query()->where('status', \App\Enums\SubmissionStatus::PendingReview->value)->count();
    $viewAs = $current ?? $hospitals->firstWhere('is_active', true) ?? $hospitals->first();

    $isQa = $user->isQa();
    $links = $isQa ? [
        ['route' => 'admin.review', 'label' => 'Review queue', 'active' => ['admin.review'], 'count' => $pendingReview],
    ] : [
        ['route' => 'admin.overview', 'label' => 'Overview', 'active' => ['admin.overview']],
        ['route' => 'admin.hospitals.index', 'label' => 'Hospitals', 'active' => ['admin.hospitals.*', 'admin.forms.*', 'hospitals.*']],
        ['route' => 'admin.review', 'label' => 'Review queue', 'active' => ['admin.review'], 'count' => $pendingReview],
        ['route' => 'admin.users.index', 'label' => 'Users & invitations', 'active' => ['admin.users.*']],
        ['route' => 'admin.deliveries', 'label' => 'Deliveries & audit', 'active' => ['admin.deliveries', 'admin.audit']],
        ['route' => 'admin.settings.typeform', 'label' => 'Typeform connection', 'active' => ['admin.settings.*']],
    ];
    // QA users review and view; the hospital switcher takes them to the dashboard instead of the management page.
    $hospitalLink = fn ($h) => $isQa ? route('hospitals.dashboard', $h) : route('admin.hospitals.show', $h);
@endphp

<div>
    <div x-show="sidebarOpen" x-transition.opacity class="fixed inset-0 z-40 bg-em-ink/40 lg:hidden" @click="sidebarOpen = false" x-cloak></div>

    <aside class="fixed inset-y-0 left-0 z-50 w-60 flex flex-col gap-[22px] px-3.5 pt-6 pb-5 bg-em-side border-r border-em-line text-em-ink text-[13px] transition-transform lg:translate-x-0 lg:sticky lg:top-0 lg:h-screen overflow-y-auto no-print"
           :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'" aria-label="Main navigation">
        <div class="flex items-center gap-[9px] px-2">
            <span class="h-8 w-8 rounded-[9px] bg-em-ink text-white flex items-center justify-center text-[21px] font-medium leading-none pb-[3px]" aria-hidden="true">e</span>
            <span class="text-[17px] font-light tracking-[0.09em]">MOTION</span>
            <span class="ml-auto em-pill bg-em-accent-soft text-em-accent text-[10.5px]">{{ $isQa ? 'QA' : 'Admin' }}</span>
            <button type="button" class="lg:hidden p-1 text-em-mute hover:text-em-ink" @click="sidebarOpen = false" aria-label="Close menu">✕</button>
        </div>

        @if($hospitals->isNotEmpty())
            <label class="flex flex-col gap-1.5 px-1">
                <span class="eyebrow uppercase px-1.5">Hospital</span>
                <select class="field cursor-pointer bg-white text-[12.5px]" aria-label="Jump to a hospital"
                        x-on:change="if ($event.target.value) { Livewire.navigate($event.target.value) }">
                    <option value="" @selected($current === null)>Jump to a hospital…</option>
                    @foreach($hospitals as $h)
                        <option value="{{ $hospitalLink($h) }}" @selected($current?->is($h))>{{ $h->name }}{{ $h->is_active ? '' : ' (inactive)' }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        <nav class="flex flex-col gap-1">
            <div class="eyebrow uppercase px-2.5 pb-1.5">{{ $isQa ? 'eMotion QA' : 'eMotion admin' }}</div>
            @foreach($links as $link)
                @php $active = request()->routeIs(...$link['active']); @endphp
                <a href="{{ route($link['route']) }}" wire:navigate class="em-nav-item {{ $active ? 'em-nav-item-active' : '' }}" @if($active) aria-current="page" @endif>
                    <span class="h-1.5 w-1.5 rounded-full shrink-0 {{ $active ? 'bg-em-accent' : 'bg-[#D9D3D9]' }}" aria-hidden="true"></span>
                    <span class="flex-1">{{ $link['label'] }}</span>
                    @if(($link['count'] ?? 0) > 0)
                        <span class="min-w-5 px-[7px] py-px rounded-full bg-em-warn-bg text-em-warn text-[11px] font-semibold text-center tabular-nums">{{ $link['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="mt-auto flex flex-col gap-3">
            @if($viewAs)
                <a href="{{ route('hospitals.dashboard', $viewAs) }}" wire:navigate class="flex items-center justify-between px-3 py-2.5 border border-em-line rounded-[10px] bg-white text-[12.5px] hover:border-em-hover"
                   title="Open {{ $viewAs->name }} as its users see it">Hospital view<span class="text-em-mute" aria-hidden="true">→</span></a>
            @endif
            <div class="flex items-center gap-2.5 px-2 pt-3 border-t border-em-line">
                <a href="{{ route('profile') }}" wire:navigate class="h-8 w-8 rounded-full bg-em-accent-soft text-em-accent flex items-center justify-center font-semibold text-xs shrink-0" aria-label="Your profile">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</a>
                <div class="flex-1 min-w-0">
                    <a href="{{ route('profile') }}" wire:navigate class="block font-medium truncate hover:text-em-accent" x-data="{{ json_encode(['name' => $user->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name">{{ $user->name }}</a>
                    <div class="text-[11.5px] text-em-mute truncate">Super admin</div>
                </div>
                <button type="button" wire:click="logout" class="p-1.5 rounded-lg text-em-mute hover:text-em-ink hover:bg-white" title="Log out" aria-label="Log out">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3V7a3 3 0 0 1 3-3h4a3 3 0 0 1 3 3v1"/></svg>
                </button>
            </div>
        </div>
    </aside>
</div>
