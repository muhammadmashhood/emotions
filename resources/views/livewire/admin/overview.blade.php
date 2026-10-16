<div>
    <x-page-header title="Overview" subtitle="All hospitals at a glance." />
    <x-page>
        @if($hospitals->isEmpty())
            <x-empty-state title="No hospitals yet" message="Create the first hospital to get started.">
                <x-slot name="action"><a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn-primary">New hospital</a></x-slot>
            </x-empty-state>
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Hospital</th><th>Active forms</th><th>Users</th></tr></thead>
                    <tbody>
                    @foreach($hospitals as $hospital)
                        <tr wire:key="h-{{ $hospital->id }}">
                            <td class="font-medium"><a href="{{ route('admin.hospitals.show', $hospital) }}" wire:navigate class="hover:underline">{{ $hospital->name }}</a></td>
                            <td class="tabular-nums">{{ $hospital->forms_count }}</td>
                            <td class="tabular-nums">{{ $hospital->users_count }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-page>
</div>
