<x-admin.page>
    <x-admin.header title="Hospitals" subtitle="Client accounts. Each hospital has its own facilities, forms, users and dashboard.">
        <x-slot name="actions">
            <a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn btn-primary">New hospital</a>
        </x-slot>
    </x-admin.header>

    <x-admin.flash />

    @if($hospitals->isEmpty())
        <div class="bg-white border border-adm-line rounded-2xl p-10 text-center">
            <p class="font-medium">No hospitals yet</p>
            <p class="text-[13px] text-adm-soft mt-1">Create a hospital, then link its Typeform surveys and invite its users.</p>
            <a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn btn-primary mt-4">New hospital</a>
        </div>
    @else
        <div class="grid gap-3.5 grid-cols-[repeat(auto-fill,minmax(320px,1fr))]">
            @foreach($hospitals as $hospital)
                @php
                    $s = $scores[$hospital->id] ?? ['avg' => null, 'previous_avg' => null, 'count' => 0];
                    $delta = $s['avg'] !== null && $s['previous_avg'] !== null ? round($s['avg'] - $s['previous_avg'], 1) : null;
                @endphp
                <article wire:key="h-{{ $hospital->id }}" class="bg-white border border-adm-line rounded-2xl p-5 flex flex-col gap-4 {{ $hospital->is_active ? '' : 'opacity-70' }}">
                    <div class="flex items-start gap-3">
                        <x-admin.avatar :name="$hospital->name" :logo="$hospital->logo_path" :seed="$hospital->uuid" />
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('admin.hospitals.show', $hospital) }}" wire:navigate class="block text-sm font-semibold truncate hover:text-adm-accent">{{ $hospital->name }}</a>
                            <div class="text-xs text-adm-soft">since {{ $hospital->created_at?->format('d M Y') ?? '—' }}{{ $hospital->require_review ? ' · review on' : '' }}</div>
                        </div>
                        @if($hospital->is_active)
                            <x-admin.pill tone="ok">Active</x-admin.pill>
                        @else
                            <x-admin.pill>Inactive</x-admin.pill>
                        @endif
                    </div>

                    <dl class="grid grid-cols-3 gap-2">
                        @foreach(['Facilities' => $hospital->branches_count, 'Forms' => $hospital->forms_count, 'Users' => $hospital->users_count] as $label => $count)
                            <div class="rounded-xl bg-[#F8F7F6] px-3 py-2.5">
                                <dd class="text-lg font-semibold tabular-nums leading-tight">{{ $count }}</dd>
                                <dt class="text-[11.5px] text-adm-mute">{{ $label }}</dt>
                            </div>
                        @endforeach
                    </dl>

                    <div class="flex items-center gap-2 mt-auto">
                        <x-admin.score :pct="$s['avg']" />
                        <span class="text-xs text-adm-soft flex-1">
                            avg score, 30 days
                            @if($delta !== null)
                                <span class="{{ $delta > 0 ? 'text-adm-ok' : ($delta < 0 ? 'text-adm-bad' : 'text-adm-mute') }}" title="vs previous 30 days">{{ $delta > 0 ? '▲ +' : ($delta < 0 ? '▼ ' : '▶ ') }}{{ number_format($delta, 1) }}</span>
                            @endif
                        </span>
                        <a href="{{ route('hospitals.dashboard', $hospital) }}" wire:navigate class="text-[12.5px] text-adm-accent hover:underline">Dashboard</a>
                        <a href="{{ route('admin.hospitals.show', $hospital) }}" wire:navigate class="btn btn-secondary btn-sm">Manage</a>
                    </div>
                </article>
            @endforeach
        </div>
        {{ $hospitals->links() }}
    @endif
</x-admin.page>
