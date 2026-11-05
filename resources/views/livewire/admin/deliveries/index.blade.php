<x-ui.page>
    <x-ui.header title="Deliveries & audit" subtitle="Every request Typeform sent us, with its signature check and processing result." />
    <x-ui.flash />

    <div class="flex flex-wrap items-center gap-2.5">
        @include('livewire.admin.partials.log-switch', ['current' => 'deliveries'])
        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Result">
            @foreach(['' => 'All', 'ok' => 'Processed', 'error' => 'Failed', 'invalid' => 'Rejected', 'pending' => 'Pending', 'duplicate' => 'Duplicates'] as $key => $label)
                <x-ui.chip :active="$status === $key" wire:click="setStatus('{{ $key }}')">{{ $label }}</x-ui.chip>
            @endforeach
        </div>
        <select wire:model.live="form" class="field w-auto min-w-52 ml-auto bg-white" aria-label="Form">
            <option value="">All forms</option>
            @foreach($forms as $f)<option value="{{ $f->uuid }}">{{ $f->title }}</option>@endforeach
        </select>
    </div>

    <div class="panel overflow-x-auto">
        <table class="table-base">
            <thead><tr><th>Received</th><th>Form · Hospital</th><th>Result</th><th>Detail</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            @forelse($deliveries as $delivery)
                @php
                    $duplicate = $delivery->error && str_starts_with($delivery->error, 'Duplicate');
                    [$tone, $result, $detail] = match (true) {
                        ! $delivery->signature_valid => ['muted', 'Rejected', 'Signature did not match or the form is unknown. Request ignored.'],
                        $duplicate => ['muted', 'Duplicate', 'Already stored. Typeform sent this response again.'],
                        (bool) $delivery->error => ['bad', 'Failed', $delivery->error],
                        (bool) $delivery->processed_at => ['ok', 'Processed', 'Response stored'.($delivery->response_token ? ' · token '.Str::limit($delivery->response_token, 14) : '')],
                        default => ['warn', 'Pending', 'Waiting in the queue'],
                    };
                @endphp
                <tr wire:key="d-{{ $delivery->id }}">
                    <td class="whitespace-nowrap"><x-ui.when :at="$delivery->received_at" /></td>
                    <td>
                        <div class="font-medium">{{ $delivery->form?->title ?? 'Unknown form' }}</div>
                        <div class="text-xs text-em-soft">{{ $delivery->form?->hospital?->name ?? 'Unknown' }}@if($delivery->event_type) · {{ $delivery->event_type }}@endif</div>
                    </td>
                    <td><x-ui.pill :tone="$tone">{{ $result }}</x-ui.pill></td>
                    <td class="max-w-[440px] {{ $tone === 'bad' ? 'text-em-bad' : '' }}">{{ Str::limit($detail, 160) }}</td>
                    <td class="text-right whitespace-nowrap space-x-1.5">
                        <button type="button" wire:click="view({{ $delivery->id }})" class="text-[12.5px] text-em-accent hover:underline">{{ $viewing === $delivery->id ? 'Hide' : 'Payload' }}</button>
                        @if($delivery->signature_valid && $delivery->error && ! $duplicate)
                            <button type="button" wire:click="retry({{ $delivery->id }})" class="btn btn-secondary btn-sm">Retry</button>
                        @endif
                    </td>
                </tr>
                @if($viewing === $delivery->id && $viewedPayload)
                    <tr wire:key="p-{{ $delivery->id }}"><td colspan="5" class="bg-em-bg"><pre class="text-xs overflow-x-auto max-h-96 p-2">{{ $viewedPayload }}</pre></td></tr>
                @endif
            @empty
                <tr><td colspan="5" class="text-center text-em-soft py-8">No deliveries here. They appear as soon as Typeform sends a response.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $deliveries->links() }}
</x-ui.page>
