<x-admin.page>
    <x-admin.header title="Overview" :subtitle="'All hospitals, facilities and incoming responses · '.now()->format('j M Y')">
        <x-slot name="actions">
            <a href="{{ route('admin.users.index', ['invite' => 1]) }}" wire:navigate class="btn btn-secondary">Invite user</a>
            <a href="{{ route('admin.hospitals.create') }}" wire:navigate class="btn btn-primary">New hospital</a>
        </x-slot>
    </x-admin.header>

    <x-admin.flash />

    <div class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(160px,1fr))]">
        <x-admin.kpi label="Active hospitals" :value="$kpis['hospitals']" :note="$kpis['hospitalTotal'].' '.Str::plural('account', $kpis['hospitalTotal']).' in total'" :href="route('admin.hospitals.index')" />
        <x-admin.kpi label="Facilities" :value="$kpis['facilities']" :note="'Across '.$kpis['facilityHospitals'].' '.Str::plural('hospital', $kpis['facilityHospitals'])" />
        <x-admin.kpi label="Awaiting review" :value="$kpis['pending']" :tone="$kpis['pending'] > 0 ? 'warn' : null" :href="route('admin.review')"
            :note="$kpis['oldestPending'] ? 'Oldest received '.$kpis['oldestPending']->diffForHumans() : 'Queue is empty'" />
        <x-admin.kpi label="Webhooks failing" :value="$kpis['webhooksFailing']" :tone="$kpis['webhooksFailing'] > 0 ? 'bad' : null" :href="route('admin.deliveries', ['status' => 'error'])"
            :note="$kpis['activeForms'].' '.Str::plural('form', $kpis['activeForms']).' linked'" />
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_386px] gap-4 items-start">
        <x-admin.card title="Needs attention" :count="$alertCount">
            @if($alertCount === 0)
                <p class="px-[22px] py-5 text-[13px] text-adm-soft">All clear: every linked form has an active webhook and a reviewed mapping, nothing is waiting for review and no deliveries failed in the last 24 hours.</p>
            @else
                <ul class="divide-y divide-adm-line">
                    @if($pendingCount > 0)
                        <x-admin.attention tone="info" tag="Review" :title="$pendingCount.' '.Str::plural('response', $pendingCount).' waiting for review'" detail="Every Typeform response is checked before it reaches a dashboard">
                            <x-slot name="action"><a href="{{ route('admin.review') }}" wire:navigate class="btn btn-secondary btn-sm">Open queue</a></x-slot>
                        </x-admin.attention>
                    @endif
                    @if($connection === null)
                        <x-admin.attention tone="bad" tag="Typeform" title="No Typeform connection configured." detail="Forms cannot be linked and responses cannot be fetched until a token is added">
                            <x-slot name="action"><a href="{{ route('admin.settings.typeform') }}" wire:navigate class="btn btn-secondary btn-sm">Connect</a></x-slot>
                        </x-admin.attention>
                    @elseif($connection->last_error)
                        <x-admin.attention tone="bad" tag="Typeform" title="Connection error" :detail="Str::limit($connection->last_error, 140)">
                            <x-slot name="action"><a href="{{ route('admin.settings.typeform') }}" wire:navigate class="btn btn-secondary btn-sm">Check connection</a></x-slot>
                        </x-admin.attention>
                    @endif
                    @foreach($reviewForms as $form)
                        <x-admin.attention tone="warn" tag="Mapping review" wire:key="rv-{{ $form->id }}" :title="$form->title.' · v'.$form->currentVersion?->version_number"
                            :detail="$form->hospital->name.' · the Typeform changed, check the new questions and scored flags'">
                            <x-slot name="action"><a href="{{ route('admin.forms.mapping', $form) }}" wire:navigate class="btn btn-secondary btn-sm">Review mapping</a></x-slot>
                        </x-admin.attention>
                    @endforeach
                    @foreach($webhookForms as $form)
                        <x-admin.attention tone="bad" :tag="'Webhook '.$form->webhook_status->label()" wire:key="wh-{{ $form->id }}" :title="$form->title"
                            :detail="$form->hospital->name.($form->webhook_last_error ? ' · '.Str::limit($form->webhook_last_error, 120) : ' · new responses are not arriving')">
                            <x-slot name="action"><button type="button" wire:click="repairWebhook('{{ $form->uuid }}')" wire:loading.attr="disabled" class="btn btn-secondary btn-sm">Repair webhook</button></x-slot>
                        </x-admin.attention>
                    @endforeach
                    @foreach($failedDeliveries as $delivery)
                        <x-admin.attention tone="bad" tag="Delivery failed" wire:key="fd-{{ $delivery->id }}" :title="($delivery->form?->title ?? 'Unknown form').' · '.$delivery->received_at->diffForHumans()"
                            :detail="Str::limit($delivery->error, 140)">
                            <x-slot name="action"><a href="{{ route('admin.deliveries', ['status' => 'error']) }}" wire:navigate class="btn btn-secondary btn-sm">View deliveries</a></x-slot>
                        </x-admin.attention>
                    @endforeach
                    @foreach($unlinkedHospitals as $hospital)
                        <x-admin.attention tone="muted" tag="No form" wire:key="nf-{{ $hospital->id }}" :title="$hospital->name" detail="No Typeform linked, so no evaluations arrive">
                            <x-slot name="action"><a href="{{ route('admin.hospitals.forms.link', $hospital) }}" wire:navigate class="btn btn-secondary btn-sm">Link a form</a></x-slot>
                        </x-admin.attention>
                    @endforeach
                </ul>
            @endif
            @if($invalidDeliveries > 0)
                <p class="px-[22px] py-2.5 text-xs text-adm-mute border-t border-adm-line">{{ $invalidDeliveries }} {{ Str::plural('request', $invalidDeliveries) }} with an invalid signature or unknown form were rejected in the last 24 hours.</p>
            @endif
        </x-admin.card>

        <x-admin.card title="Recent activity">
            <x-slot name="actions"><a href="{{ route('admin.audit') }}" wire:navigate class="text-adm-accent hover:underline">Audit log</a></x-slot>
            @if($activity->isEmpty())
                <p class="px-[22px] py-5 text-[13px] text-adm-soft">Nothing yet. Logins, invitations, form links and review decisions appear here.</p>
            @else
                <ul class="px-[22px] pb-2">
                    @foreach($activity as $entry)
                        <li class="py-3.5 border-b border-adm-line last:border-b-0" wire:key="act-{{ $entry->id }}">
                            <div class="text-[13px] font-medium">{{ $entry->label() }}</div>
                            <div class="text-[12.5px] text-adm-ink/90">{{ $entry->summary() }}</div>
                            <div class="text-[11.5px] text-adm-mute">{{ $entry->created_at->diffForHumans() }} · {{ $entry->user?->name ?? 'System' }}</div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-admin.card>
    </div>
</x-admin.page>
