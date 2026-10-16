<div>
    <x-page-header title="Hospitals" subtitle="Client accounts. Each hospital has its own users, forms and dashboard.">
        <x-slot name="actions">
            <a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn-primary">New hospital</a>
        </x-slot>
    </x-page-header>

    <x-page>
        <x-flash />
        <div class="flex items-center gap-3">
            <label for="search" class="sr-only">Search hospitals</label>
            <input id="search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search hospitals…" class="field max-w-sm">
        </div>

        @if($hospitals->isEmpty())
            <x-empty-state title="No hospitals yet" message="Create a hospital, then link its Typeform surveys and invite its users.">
                <x-slot name="action"><a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn-primary">New hospital</a></x-slot>
            </x-empty-state>
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Hospital</th><th>Status</th><th>Active forms</th><th>Users</th><th></th></tr></thead>
                    <tbody>
                        @foreach($hospitals as $hospital)
                            <tr wire:key="h-{{ $hospital->id }}">
                                <td class="font-medium"><a href="{{ route('admin.hospitals.show', $hospital) }}" wire:navigate class="hover:underline">{{ $hospital->name }}</a></td>
                                <td>@if($hospital->is_active)<x-badge variant="green">Active</x-badge>@else<x-badge variant="red">Inactive</x-badge>@endif</td>
                                <td class="tabular-nums">{{ $hospital->forms_count }}</td>
                                <td class="tabular-nums">{{ $hospital->users_count }}</td>
                                <td class="text-right">@if(Route::has('hospitals.dashboard'))<a href="{{ route('hospitals.dashboard', $hospital) }}" wire:navigate class="text-sm text-ink-soft hover:text-ink underline">Dashboard</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $hospitals->links() }}
        @endif
    </x-page>
</div>
