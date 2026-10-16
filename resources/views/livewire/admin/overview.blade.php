<div>
    <x-page-header title="Overview" :subtitle="'All hospitals · '.$filters->label()">
        <x-slot name="actions">
            <div class="inline-flex rounded-md border border-line overflow-hidden" role="group" aria-label="Period">
                @foreach(['30d' => '30 days', '90d' => '90 days', '12m' => '12 months'] as $key => $label)
                    <button type="button" wire:click="setPeriod('{{ $key }}')" class="px-3 py-2 text-sm {{ $period === $key ? 'bg-ink text-white' : 'bg-white text-ink-soft hover:bg-paper' }}">{{ $label }}</button>
                @endforeach
            </div>
            <a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn-primary">New hospital</a>
        </x-slot>
    </x-page-header>

    <x-page>
        <x-flash />

        <x-card :title="'Alerts'.($alertCount > 0 ? ' ('.$alertCount.')' : '')" :padding="false">
            @if($alertCount === 0)
                <p class="p-4 text-sm text-ink-soft">All clear: every linked form has an active webhook and a reviewed mapping, and no deliveries failed in the last 24 hours.</p>
            @else
                <ul class="divide-y divide-line text-sm">
                    @if($connection === null)
                        <li class="p-4 flex flex-wrap items-center justify-between gap-2"><span><x-badge variant="red">Typeform</x-badge> No Typeform connection configured.</span><a href="{{ route('admin.settings.typeform') }}" wire:navigate class="underline">Connect</a></li>
                    @elseif($connection->last_error)
                        <li class="p-4 flex flex-wrap items-center justify-between gap-2"><span><x-badge variant="red">Typeform</x-badge> Connection error: {{ Str::limit($connection->last_error, 120) }}</span><a href="{{ route('admin.settings.typeform') }}" wire:navigate class="underline">Check connection</a></li>
                    @endif
                    @foreach($webhookForms as $form)
                        <li class="p-4 flex flex-wrap items-center justify-between gap-2" wire:key="wh-{{ $form->id }}">
                            <span><x-badge variant="red">Webhook {{ $form->webhook_status->label() }}</x-badge> <a href="{{ route('admin.forms.show', $form) }}" wire:navigate class="font-medium hover:underline">{{ $form->title }}</a> <span class="text-ink-soft">· {{ $form->hospital->name }}</span>@if($form->webhook_last_error)<span class="block text-xs text-rag-red">{{ Str::limit($form->webhook_last_error, 140) }}</span>@endif</span>
                            <button type="button" wire:click="repairWebhook('{{ $form->uuid }}')" wire:loading.attr="disabled" class="btn-secondary">Repair webhook</button>
                        </li>
                    @endforeach
                    @foreach($reviewForms as $form)
                        <li class="p-4 flex flex-wrap items-center justify-between gap-2" wire:key="rv-{{ $form->id }}">
                            <span><x-badge variant="amber">Mapping review</x-badge> <a href="{{ route('admin.forms.show', $form) }}" wire:navigate class="font-medium hover:underline">{{ $form->title }}</a> <span class="text-ink-soft">· {{ $form->hospital->name }} · v{{ $form->currentVersion?->version_number }}</span></span>
                            <a href="{{ route('admin.forms.mapping', $form) }}" wire:navigate class="btn-secondary">Review mapping</a>
                        </li>
                    @endforeach
                    @foreach($failedDeliveries as $delivery)
                        <li class="p-4 flex flex-wrap items-center justify-between gap-2" wire:key="fd-{{ $delivery->id }}">
                            <span><x-badge variant="red">Delivery failed</x-badge> {{ $delivery->form?->title ?? 'Unknown form' }} <span class="text-ink-soft">· {{ $delivery->received_at->diffForHumans() }}</span><span class="block text-xs text-rag-red">{{ Str::limit($delivery->error, 140) }}</span></span>
                            <a href="{{ route('admin.deliveries', ['status' => 'error']) }}" wire:navigate class="btn-secondary">Open deliveries</a>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if($invalidDeliveries > 0)
                <p class="px-4 py-2 text-xs text-ink-soft border-t border-line">{{ $invalidDeliveries }} {{ Str::plural('request', $invalidDeliveries) }} with an invalid signature or unknown form were rejected in the last 24 hours.</p>
            @endif
        </x-card>

        @if($rows->isEmpty())
            <x-empty-state title="No hospitals yet" message="Create the first hospital to get started.">
                <x-slot name="action"><a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn-primary">New hospital</a></x-slot>
            </x-empty-state>
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Hospital</th><th>Active forms</th><th>Evaluations</th><th>Average score</th><th>Trend</th><th>Mapping review</th><th>Webhook problems</th><th></th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                        @php $h = $row['hospital']; $s = $row['stats']; @endphp
                        <tr wire:key="h-{{ $h->id }}" class="{{ $h->is_active ? '' : 'opacity-60' }}">
                            <td class="font-medium"><a href="{{ route('admin.hospitals.show', $h) }}" wire:navigate class="hover:underline">{{ $h->name }}</a>@unless($h->is_active) <x-badge variant="red">Inactive</x-badge>@endunless</td>
                            <td class="tabular-nums">{{ $h->active_forms_count }}</td>
                            <td class="tabular-nums">{{ $s['count'] }}</td>
                            <td><x-rag-pill :pct="$s['avg']" /></td>
                            <td class="tabular-nums">
                                @if($row['delta'] === null)
                                    <span class="text-ink-soft">–</span>
                                @elseif($row['delta'] > 0)
                                    <span class="text-rag-green" title="vs previous period">▲ +{{ number_format($row['delta'], 1) }}</span>
                                @elseif($row['delta'] < 0)
                                    <span class="text-rag-red" title="vs previous period">▼ {{ number_format($row['delta'], 1) }}</span>
                                @else
                                    <span class="text-ink-soft">▶ 0.0</span>
                                @endif
                            </td>
                            <td>@if($h->review_forms_count > 0)<x-badge variant="amber">{{ $h->review_forms_count }}</x-badge>@else<span class="text-ink-soft">–</span>@endif</td>
                            <td>@if($h->webhook_problem_count > 0)<x-badge variant="red">{{ $h->webhook_problem_count }}</x-badge>@else<span class="text-ink-soft">–</span>@endif</td>
                            <td class="text-right"><a href="{{ route('hospitals.dashboard', $h) }}" wire:navigate class="text-sm underline text-ink-soft hover:text-ink">Dashboard</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-page>
</div>
