<div>
    <x-page-header title="Audit log" subtitle="Who did what, when: logins, invitations, form links, mapping changes, re-scores and review decisions." />
    <x-page wide>
        <div class="panel p-3 flex flex-wrap gap-3 items-end">
            <div>
                <label class="label" for="action">Action</label>
                <select id="action" wire:model.live="action" class="field w-auto">
                    <option value="">All actions</option>
                    @foreach($actionPrefixes as $prefix)<option value="{{ $prefix }}">{{ $prefix }}.*</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="hospital">Hospital</label>
                <select id="hospital" wire:model.live="hospital" class="field w-auto">
                    <option value="">All hospitals</option>
                    @foreach($hospitals as $h)<option value="{{ $h->uuid }}">{{ $h->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label" for="user">User</label>
                <input id="user" type="search" wire:model.live.debounce.400ms="user" class="field" placeholder="Name or email">
            </div>
            <div>
                <label class="label" for="from">From</label>
                <input id="from" type="date" wire:model.live="from" class="field">
            </div>
            <div>
                <label class="label" for="to">To</label>
                <input id="to" type="date" wire:model.live="to" class="field">
            </div>
        </div>

        <div class="panel overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>When</th><th>Action</th><th>User</th><th>Hospital</th><th>Subject</th><th>IP</th><th></th></tr></thead>
                <tbody>
                @forelse($logs as $log)
                    <tr wire:key="log-{{ $log->id }}">
                        <td class="whitespace-nowrap text-ink-soft">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                        <td><code class="text-xs">{{ $log->action }}</code></td>
                        <td>{{ $log->user?->name ?? 'system' }}@if($log->user)<span class="block text-xs text-ink-soft">{{ $log->user->email }}</span>@endif</td>
                        <td>{{ $log->hospital?->name ?? '—' }}</td>
                        <td class="text-xs text-ink-soft">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '—' }}</td>
                        <td class="text-xs text-ink-soft">{{ $log->ip ?? '—' }}</td>
                        <td class="text-right">@if($log->changes)<button type="button" wire:click="toggle({{ $log->id }})" class="text-sm underline">{{ $expanded === $log->id ? 'Hide' : 'Details' }}</button>@endif</td>
                    </tr>
                    @if($expanded === $log->id && $log->changes)
                        <tr wire:key="logd-{{ $log->id }}"><td colspan="7" class="bg-paper"><pre class="text-xs overflow-x-auto max-h-80 p-2">{{ json_encode($log->changes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre></td></tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="text-center text-ink-soft py-6">No audit entries match these filters.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </x-page>
</div>
