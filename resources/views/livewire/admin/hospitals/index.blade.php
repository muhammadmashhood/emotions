<x-ui.page>
    <x-ui.header title="Hospitals" subtitle="Client accounts. Each hospital has its own facilities, forms, users and dashboard.">
        <x-slot name="actions">
            <a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn btn-primary">New hospital</a>
        </x-slot>
    </x-ui.header>

    <x-ui.flash />

    @if($hospitals->isEmpty())
        <div class="bg-white border border-em-line rounded-2xl p-10 text-center">
            <p class="font-medium">No hospitals yet</p>
            <p class="text-[13px] text-em-soft mt-1">Create a hospital, then link its Typeform surveys and invite its users.</p>
            <a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn btn-primary mt-4">New hospital</a>
        </div>
    @else
        <div class="grid gap-3.5 grid-cols-[repeat(auto-fill,minmax(320px,1fr))]">
            @foreach($hospitals as $hospital)
                @php
                    $s = $scores[$hospital->id] ?? ['avg' => null, 'previous_avg' => null, 'count' => 0];
                    $delta = $s['avg'] !== null && $s['previous_avg'] !== null ? round($s['avg'] - $s['previous_avg'], 1) : null;
                @endphp
                <article wire:key="h-{{ $hospital->id }}" class="bg-white border border-em-line rounded-2xl p-5 flex flex-col gap-4 {{ $hospital->is_active ? '' : 'opacity-70' }}">
                    <div class="flex items-start gap-3">
                        <x-ui.avatar :name="$hospital->name" :logo="$hospital->logo_path" :seed="$hospital->uuid" />
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('admin.hospitals.show', $hospital) }}" wire:navigate class="block text-sm font-semibold truncate hover:text-em-accent">{{ $hospital->name }}</a>
                            <div class="text-xs text-em-soft">since {{ $hospital->created_at?->format('d M Y') ?? '—' }}{{ $hospital->require_review ? ' · review on' : '' }}</div>
                        </div>
                        @if($hospital->is_active)
                            <x-ui.pill tone="ok">Active</x-ui.pill>
                        @else
                            <x-ui.pill>Inactive</x-ui.pill>
                        @endif
                    </div>

                    <dl class="grid grid-cols-3 gap-2">
                        @foreach(['Facilities' => $hospital->branches_count, 'Forms' => $hospital->forms_count, 'Users' => $hospital->users_count] as $label => $count)
                            <div class="rounded-xl bg-[#F8F7F6] px-3 py-2.5">
                                <dd class="text-lg font-semibold tabular-nums leading-tight">{{ $count }}</dd>
                                <dt class="text-[11.5px] text-em-mute">{{ $label }}</dt>
                            </div>
                        @endforeach
                    </dl>

                    <div class="flex items-center gap-2 mt-auto">
                        <x-ui.score :pct="$s['avg']" />
                        <span class="text-xs text-em-soft flex-1">
                            avg score, 30 days
                            @if($delta !== null)
                                <span class="{{ $delta > 0 ? 'text-em-ok' : ($delta < 0 ? 'text-em-bad' : 'text-em-mute') }}" title="vs previous 30 days">{{ $delta > 0 ? '▲ +' : ($delta < 0 ? '▼ ' : '▶ ') }}{{ number_format($delta, 1) }}</span>
                            @endif
                        </span>
                        <a href="{{ route('hospitals.dashboard', $hospital) }}" wire:navigate class="text-[12.5px] text-em-accent hover:underline">Dashboard</a>
                        <a href="{{ route('admin.hospitals.show', $hospital) }}" wire:navigate class="btn btn-secondary btn-sm">Manage</a>
                    </div>
                </article>
            @endforeach
        </div>
        {{ $hospitals->links() }}
    @endif
</x-ui.page>
