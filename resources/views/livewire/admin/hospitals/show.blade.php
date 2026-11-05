<x-admin.page>
    <x-admin.header :title="$hospital->name" :back="route('admin.hospitals.index')" back-label="Hospitals"
        :subtitle="($hospital->is_active ? 'Active account' : 'Inactive · its users cannot log in').' · since '.($hospital->created_at?->format('d M Y') ?? '—').($hospital->require_review ? ' · review required' : '')">
        <x-slot name="leading"><x-admin.avatar :name="$hospital->name" :logo="$hospital->logo_path" :seed="$hospital->uuid" size="lg" /></x-slot>
        <x-slot name="actions">
            <a href="{{ route('hospitals.dashboard', $hospital) }}" wire:navigate class="btn btn-secondary">Open dashboard</a>
            <a href="{{ route('admin.hospitals.forms.link', $hospital) }}" wire:navigate class="btn btn-primary">Link a form</a>
        </x-slot>
    </x-admin.header>

    <x-admin.flash />
    <x-admin.tabs :current="$tab" :tabs="[
        'overview' => 'Overview',
        'facilities' => ['label' => 'Facilities', 'count' => $hospital->branches_count],
        'forms' => ['label' => 'Forms', 'count' => $hospital->forms_count],
        'users' => ['label' => 'Users', 'count' => $hospital->users_count],
        'settings' => 'Settings',
    ]" />

    @if($tab === 'overview')
        @php $attention = ($pendingCount > 0 ? 1 : 0) + $reviewForms->count() + $webhookForms->count() + ($hospital->forms_count === 0 ? 1 : 0); @endphp
        <div class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(160px,1fr))]">
            <x-admin.kpi label="Facilities" :value="$hospital->branches_count" />
            <x-admin.kpi label="Linked forms" :value="$hospital->forms_count" />
            <x-admin.kpi label="Users" :value="$hospital->users_count" />
            <x-admin.kpi label="Evaluations" :value="number_format($hospital->submissions_count)" />
        </div>

        <x-admin.card title="Setup & attention">
            @if($attention === 0)
                <p class="px-[22px] py-5 text-[13px] text-adm-soft">Nothing needs attention. Webhooks are active, mappings are reviewed and the queue is empty.</p>
            @else
                <ul class="divide-y divide-adm-line">
                    @if($pendingCount > 0)
                        <x-admin.attention tone="info" tag="Review" :title="$pendingCount.' '.Str::plural('response', $pendingCount).' waiting for review'" detail="Every Typeform response is checked before it reaches a dashboard">
                            <x-slot name="action"><a href="{{ route('admin.review', ['hospital' => $hospital->uuid]) }}" wire:navigate class="btn btn-secondary btn-sm">Open queue</a></x-slot>
                        </x-admin.attention>
                    @endif
                    @foreach($reviewForms as $form)
                        <x-admin.attention tone="warn" tag="Mapping review" wire:key="rv-{{ $form->id }}" :title="$form->title.' · v'.$form->currentVersion?->version_number" detail="The Typeform changed. Check the new questions and scored flags">
                            <x-slot name="action"><a href="{{ route('admin.forms.mapping', $form) }}" wire:navigate class="btn btn-secondary btn-sm">Review mapping</a></x-slot>
                        </x-admin.attention>
                    @endforeach
                    @foreach($webhookForms as $form)
                        <x-admin.attention tone="bad" :tag="'Webhook '.$form->webhook_status->label()" wire:key="wh-{{ $form->id }}" :title="$form->title" :detail="$form->webhook_last_error ? Str::limit($form->webhook_last_error, 140) : 'New responses are not arriving'">
                            <x-slot name="action"><a href="{{ route('admin.forms.show', $form) }}" wire:navigate class="btn btn-secondary btn-sm">Open form</a></x-slot>
                        </x-admin.attention>
                    @endforeach
                    @if($hospital->forms_count === 0)
                        <x-admin.attention tone="muted" tag="No form" :title="$hospital->name" detail="No Typeform linked, so no evaluations arrive">
                            <x-slot name="action"><a href="{{ route('admin.hospitals.forms.link', $hospital) }}" wire:navigate class="btn btn-secondary btn-sm">Link a form</a></x-slot>
                        </x-admin.attention>
                    @endif
                </ul>
            @endif
        </x-admin.card>
    @elseif($tab === 'facilities')
        @if($branches->isEmpty())
            <div class="bg-white border border-adm-line rounded-2xl p-10 text-center">
                <p class="font-medium">No facilities yet</p>
                <p class="text-[13px] text-adm-soft mt-1">Facilities are created from the branch question of each linked form. Set the branch source on a form to start grouping evaluations.</p>
            </div>
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Facility</th><th>Code</th><th>Status</th><th>Evaluations</th><th>Last evaluation</th></tr></thead>
                    <tbody>
                    @foreach($branches as $branch)
                        <tr wire:key="b-{{ $branch->id }}">
                            <td class="font-medium">{{ $branch->name }}</td>
                            <td class="text-adm-soft">{{ $branch->code ?? '—' }}</td>
                            <td>@if($branch->is_active)<x-admin.pill tone="ok">Active</x-admin.pill>@else<x-admin.pill>Inactive</x-admin.pill>@endif</td>
                            <td class="tabular-nums">{{ $branch->submissions_count }}</td>
                            <td class="text-adm-soft">{{ $branch->submissions_max_submitted_at ? \Illuminate\Support\Carbon::parse($branch->submissions_max_submitted_at)->diffForHumans() : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif($tab === 'forms')
        @if($forms->isEmpty())
            <div class="bg-white border border-adm-line rounded-2xl p-10 text-center">
                <p class="font-medium">No forms linked</p>
                <p class="text-[13px] text-adm-soft mt-1">Link a Typeform survey to start receiving evaluations for this hospital.</p>
                <a href="{{ route('admin.hospitals.forms.link', $hospital) }}" wire:navigate class="btn btn-primary mt-4">Link a form</a>
            </div>
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Form</th><th>Version</th><th>Webhook</th><th>Responses</th><th>Last sync</th><th></th></tr></thead>
                    <tbody>
                    @foreach($forms as $form)
                        <tr wire:key="f-{{ $form->id }}" class="{{ $form->isActive() ? '' : 'opacity-60' }}">
                            <td>
                                <a href="{{ route('admin.forms.show', $form) }}" wire:navigate class="font-medium hover:text-adm-accent">{{ $form->title }}</a>
                                <div class="text-xs text-adm-soft">Typeform {{ $form->typeform_form_id }}@unless($form->isActive()) · unlinked @endunless</div>
                            </td>
                            <td class="whitespace-nowrap">v{{ $form->currentVersion?->version_number ?? '–' }} @if($form->currentVersion?->needs_review)<x-admin.pill tone="warn">Needs review</x-admin.pill>@endif</td>
                            <td>
                                @if(! $form->isActive())<x-admin.pill>{{ $form->webhook_status->label() }}</x-admin.pill>
                                @elseif($form->webhook_status === \App\Enums\WebhookStatus::Active)<x-admin.pill tone="ok">Active</x-admin.pill>
                                @else<x-admin.pill tone="bad">{{ $form->webhook_status->label() }}</x-admin.pill>@endif
                            </td>
                            <td class="tabular-nums">{{ $form->submissions_count }}</td>
                            <td class="text-adm-soft">{{ $form->last_synced_at?->diffForHumans() ?? '–' }}</td>
                            <td class="text-right"><a href="{{ route('admin.forms.mapping', $form) }}" wire:navigate class="text-adm-accent hover:underline whitespace-nowrap">Mapping →</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif($tab === 'users')
        <livewire:shared.user-management :hospital="$hospital" wire:key="users-{{ $hospital->id }}" />
    @elseif($tab === 'settings')
        <form wire:submit="saveSettings" class="panel p-6 space-y-5 max-w-2xl">
            <div>
                <label class="label" for="name">Hospital name</label>
                <input id="name" type="text" wire:model="name" class="field" required>
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>
            <div>
                <label class="label" for="logo">Logo</label>
                @if($hospital->logo_path)
                    <img src="{{ Storage::disk('public')->url($hospital->logo_path) }}" alt="" class="h-12 mb-2 rounded border border-line bg-white p-1">
                @endif
                <input id="logo" type="file" wire:model="logo" accept="image/*" class="block text-sm text-ink-soft">
                <x-input-error :messages="$errors->get('logo')" class="mt-1" />
            </div>
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="is_active" class="mt-1 rounded border-line text-ink focus:ring-ink">
                <span><span class="block text-sm font-medium text-ink">Active</span><span class="block text-xs text-ink-soft">Inactive hospitals cannot log in. Surveys are still received and stored.</span></span>
            </label>
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="require_review" class="mt-1 rounded border-line text-ink focus:ring-ink">
                <span><span class="block text-sm font-medium text-ink">Require review</span><span class="block text-xs text-ink-soft">New evaluations wait in the review queue until approved.</span></span>
            </label>
            <div>
                <label class="label" for="workspace">Typeform workspace (automatic linking)</label>
                <select id="workspace" wire:model="workspace" class="field">
                    <option value="">— none —</option>
                    @foreach($workspaces as $ws)<option value="{{ $ws->id }}">{{ $ws->name }}</option>@endforeach
                    @if($workspace !== '' && collect($workspaces)->firstWhere('id', $workspace) === null)<option value="{{ $workspace }}">{{ $hospital->typeform_workspace_name ?? $workspace }}</option>@endif
                </select>
                <p class="text-xs text-ink-soft mt-1">New forms created in this workspace are linked to {{ $hospital->name }} automatically (hourly). Their mapping still needs your review.</p>
            </div>
            <div class="flex justify-end"><button type="submit" class="btn-primary">Save settings</button></div>
        </form>
    @endif
</x-admin.page>
