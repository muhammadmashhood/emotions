<x-admin.page>
    <x-admin.header title="Deliveries & audit" subtitle="Who did what, when: logins, invitations, form links, mapping changes, re-scores and review decisions." />

    <div class="flex flex-wrap items-center gap-2.5">
        @include('livewire.admin.partials.log-switch', ['current' => 'audit'])
    </div>

    <div class="bg-white border border-adm-line rounded-2xl p-3 grid gap-2.5 grid-cols-2 md:grid-cols-[repeat(5,minmax(0,1fr))]">
        <div>
            <label class="sr-only" for="action">Action</label>
            <select id="action" wire:model.live="action" class="field">
                <option value="">All actions</option>
                @foreach($actionPrefixes as $prefix)<option value="{{ $prefix }}">{{ Str::ucfirst($prefix) }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="sr-only" for="hospital">Hospital</label>
            <select id="hospital" wire:model.live="hospital" class="field">
                <option value="">All hospitals</option>
                @foreach($hospitals as $h)<option value="{{ $h->uuid }}">{{ $h->name }}</option>@endforeach
            </select>
        </div>
        <div>
            <label class="sr-only" for="user">User</label>
            <input id="user" type="search" wire:model.live.debounce.400ms="user" class="field" placeholder="Name or email">
        </div>
        <div>
            <label class="sr-only" for="from">From</label>
            <input id="from" type="date" wire:model.live="from" class="field" title="From">
        </div>
        <div>
            <label class="sr-only" for="to">To</label>
            <input id="to" type="date" wire:model.live="to" class="field" title="To">
        </div>
    </div>

    <div class="panel overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Details</th><th><span class="sr-only">More</span></th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr wire:key="log-{{ $log->id }}">
                    <td class="whitespace-nowrap text-adm-soft"><x-admin.when :at="$log->created_at" /></td>
                    <td>{{ $log->user?->name ?? 'System' }}@if($log->user)<span class="block text-xs text-adm-mute">{{ $log->user->email }}</span>@endif</td>
                    <td><span class="font-semibold">{{ $log->label() }}</span><code class="block text-[11px] text-adm-mute">{{ $log->action }}</code></td>
                    <td>{{ $log->summary() }}@if($log->ip)<span class="block text-xs text-adm-mute">IP {{ $log->ip }}</span>@endif</td>
                    <td class="text-right">@if($log->changes)<button type="button" wire:click="toggle({{ $log->id }})" class="text-[12.5px] text-adm-accent hover:underline">{{ $expanded === $log->id ? 'Hide' : 'Details' }}</button>@endif</td>
                </tr>
                @if($expanded === $log->id && $log->changes)
                    <tr wire:key="logd-{{ $log->id }}"><td colspan="5" class="bg-adm-bg"><pre class="text-xs overflow-x-auto max-h-80 p-2">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></td></tr>
                @endif
            @empty
                <tr><td colspan="5" class="text-center text-adm-soft py-8">No audit entries match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</x-admin.page>
