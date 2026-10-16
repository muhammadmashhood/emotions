<div>
    <x-page-header title="Webhook deliveries" subtitle="Every request Typeform sent us, with its verification and processing result." />
    <x-page wide>
        <x-flash />
        <div class="flex flex-wrap gap-3">
            <select wire:model.live="status" class="field w-auto" aria-label="Status">
                <option value="">All deliveries</option>
                <option value="ok">Processed</option>
                <option value="duplicate">Duplicates</option>
                <option value="pending">Pending</option>
                <option value="error">Failed</option>
                <option value="invalid">Invalid signature / unknown form</option>
            </select>
            <select wire:model.live="form" class="field w-auto" aria-label="Form">
                <option value="">All forms</option>
                @foreach($forms as $f)<option value="{{ $f->uuid }}">{{ $f->title }}</option>@endforeach
            </select>
        </div>

        <div class="panel overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Received</th><th>Form</th><th>Event</th><th>Token</th><th>Signature</th><th>Result</th><th></th></tr></thead>
                <tbody>
                @forelse($deliveries as $delivery)
                    <tr wire:key="d-{{ $delivery->id }}">
                        <td class="whitespace-nowrap text-ink-soft">{{ $delivery->received_at->format('d M H:i:s') }}</td>
                        <td>{{ $delivery->form?->title ?? '—' }}</td>
                        <td class="text-xs">{{ $delivery->event_type ?? '—' }}</td>
                        <td class="font-mono text-xs">{{ $delivery->response_token ? Str::limit($delivery->response_token, 14) : '—' }}</td>
                        <td>@if($delivery->signature_valid)<x-badge variant="green">Valid</x-badge>@else<x-badge variant="red">Invalid</x-badge>@endif</td>
                        <td>
                            @if(! $delivery->signature_valid)
                                <x-badge variant="red">Rejected</x-badge>
                            @elseif($delivery->error && str_starts_with($delivery->error, 'Duplicate'))
                                <x-badge>Duplicate</x-badge>
                            @elseif($delivery->error)
                                <x-badge variant="red">Failed</x-badge> <span class="text-xs text-rag-red">{{ Str::limit($delivery->error, 80) }}</span>
                            @elseif($delivery->processed_at)
                                <x-badge variant="green">Processed</x-badge>
                            @else
                                <x-badge variant="amber">Pending</x-badge>
                            @endif
                        </td>
                        <td class="text-right whitespace-nowrap space-x-3">
                            <button type="button" wire:click="view({{ $delivery->id }})" class="text-sm underline">{{ $viewing === $delivery->id ? 'Hide' : 'Payload' }}</button>
                            @if($delivery->signature_valid && $delivery->error && ! str_starts_with($delivery->error, 'Duplicate'))
                                <button type="button" wire:click="retry({{ $delivery->id }})" class="text-sm underline text-ink">Retry</button>
                            @endif
                        </td>
                    </tr>
                    @if($viewing === $delivery->id && $viewedPayload)
                        <tr wire:key="p-{{ $delivery->id }}"><td colspan="7" class="bg-paper"><pre class="text-xs overflow-x-auto max-h-96 p-2">{{ $viewedPayload }}</pre></td></tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="text-center text-ink-soft py-6">No deliveries yet. They appear here as soon as Typeform sends a response.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $deliveries->links() }}
    </x-page>
</div>
