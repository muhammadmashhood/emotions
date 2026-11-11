<x-ui.page>
    <x-ui.header :title="$hospital->name" :back="route('admin.hospitals.index')" back-label="Hospitals"
        :subtitle="($hospital->is_active ? 'Active account' : 'Inactive · its users cannot log in').' · since '.($hospital->created_at?->format('d M Y') ?? '—').($hospital->require_review ? ' · review required' : '')">
        <x-slot name="leading"><x-ui.avatar :name="$hospital->name" :logo="$hospital->logo_path" :seed="$hospital->uuid" size="lg" /></x-slot>
        <x-slot name="actions">
            <a href="{{ route('hospitals.dashboard', $hospital) }}" wire:navigate class="btn btn-secondary">Open dashboard</a>
            <a href="{{ route('admin.hospitals.forms.link', $hospital) }}" wire:navigate class="btn btn-primary">Link a form</a>
        </x-slot>
    </x-ui.header>

    <x-ui.flash />
    <x-ui.tabs :current="$tab" :tabs="[
        'overview' => 'Overview',
        'facilities' => ['label' => 'Facilities', 'count' => $hospital->branches_count],
        'forms' => ['label' => 'Forms', 'count' => $hospital->forms_count],
        'users' => ['label' => 'Users', 'count' => $hospital->users_count],
        'settings' => 'Settings',
    ]" />

    @if($tab === 'overview')
        @php $attention = ($pendingCount > 0 ? 1 : 0) + $reviewForms->count() + $webhookForms->count() + ($hospital->forms_count === 0 ? 1 : 0); @endphp
        <div class="grid gap-3.5 grid-cols-[repeat(auto-fit,minmax(160px,1fr))]">
            <x-ui.kpi label="Facilities" :value="$hospital->branches_count" />
            <x-ui.kpi label="Linked forms" :value="$hospital->forms_count" />
            <x-ui.kpi label="Users" :value="$hospital->users_count" />
            <x-ui.kpi label="Evaluations" :value="number_format($hospital->submissions_count)" />
        </div>

        <x-ui.card title="Setup & attention">
            @if($attention === 0)
                <p class="px-[22px] py-5 text-[13px] text-em-soft">Nothing needs attention. Webhooks are active, mappings are reviewed and the queue is empty.</p>
            @else
                <ul class="divide-y divide-em-line">
                    @if($pendingCount > 0)
                        <x-ui.attention tone="info" tag="Review" :title="$pendingCount.' '.Str::plural('response', $pendingCount).' waiting for review'" detail="Every Typeform response is checked before it reaches a dashboard">
                            <x-slot name="action"><a href="{{ route('admin.review', ['hospital' => $hospital->uuid]) }}" wire:navigate class="btn btn-secondary btn-sm">Open queue</a></x-slot>
                        </x-ui.attention>
                    @endif
                    @foreach($reviewForms as $form)
                        <x-ui.attention tone="warn" tag="Mapping review" wire:key="rv-{{ $form->id }}" :title="$form->title.' · v'.$form->currentVersion?->version_number" detail="The Typeform changed. Check the new questions and scored flags">
                            <x-slot name="action"><a href="{{ route('admin.forms.mapping', $form) }}" wire:navigate class="btn btn-secondary btn-sm">Review mapping</a></x-slot>
                        </x-ui.attention>
                    @endforeach
                    @foreach($webhookForms as $form)
                        <x-ui.attention tone="bad" :tag="'Webhook '.$form->webhook_status->label()" wire:key="wh-{{ $form->id }}" :title="$form->title" :detail="$form->webhook_last_error ? Str::limit($form->webhook_last_error, 140) : 'New responses are not arriving'">
                            <x-slot name="action"><a href="{{ route('admin.forms.show', $form) }}" wire:navigate class="btn btn-secondary btn-sm">Open form</a></x-slot>
                        </x-ui.attention>
                    @endforeach
                    @if($hospital->forms_count === 0)
                        <x-ui.attention tone="muted" tag="No form" :title="$hospital->name" detail="No Typeform linked, so no evaluations arrive">
                            <x-slot name="action"><a href="{{ route('admin.hospitals.forms.link', $hospital) }}" wire:navigate class="btn btn-secondary btn-sm">Link a form</a></x-slot>
                        </x-ui.attention>
                    @endif
                </ul>
            @endif
        </x-ui.card>

        <livewire:hospital.mini-dashboard :hospital="$hospital" wire:key="mini-{{ $hospital->id }}" />
    @elseif($tab === 'facilities')
        @if($branches->isEmpty())
            <div class="bg-white border border-em-line rounded-2xl p-10 text-center">
                <p class="font-medium">No facilities yet</p>
                <p class="text-[13px] text-em-soft mt-1">Facilities are created from the branch question of each linked form. Set the branch source on a form to start grouping evaluations.</p>
            </div>
        @else
            <div class="panel overflow-x-auto">
                <table class="table-base">
                    <thead><tr><th>Facility</th><th>Code</th><th>Status</th><th>Evaluations</th><th>Last evaluation</th></tr></thead>
                    <tbody>
                    @foreach($branches as $branch)
                        <tr wire:key="b-{{ $branch->id }}">
                            <td class="font-medium">{{ $branch->name }}</td>
                            <td class="text-em-soft">{{ $branch->code ?? '—' }}</td>
                            <td>@if($branch->is_active)<x-ui.pill tone="ok">Active</x-ui.pill>@else<x-ui.pill>Inactive</x-ui.pill>@endif</td>
                            <td class="tabular-nums">{{ $branch->submissions_count }}</td>
                            <td class="text-em-soft">{{ $branch->submissions_max_submitted_at ? \Illuminate\Support\Carbon::parse($branch->submissions_max_submitted_at)->diffForHumans() : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @elseif($tab === 'forms')
        @if($forms->isEmpty())
            <div class="bg-white border border-em-line rounded-2xl p-10 text-center">
                <p class="font-medium">No forms linked</p>
                <p class="text-[13px] text-em-soft mt-1">Link a Typeform survey to start receiving evaluations for this hospital.</p>
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
                                <a href="{{ route('admin.forms.show', $form) }}" wire:navigate class="font-medium hover:text-em-accent">{{ $form->title }}</a>
                                <div class="text-xs text-em-soft">Typeform {{ $form->typeform_form_id }}@unless($form->isActive()) · unlinked @endunless</div>
                            </td>
                            <td class="whitespace-nowrap">v{{ $form->currentVersion?->version_number ?? '–' }} @if($form->currentVersion?->needs_review)<x-ui.pill tone="warn">Needs review</x-ui.pill>@endif</td>
                            <td>
                                @if(! $form->isActive())<x-ui.pill>{{ $form->webhook_status->label() }}</x-ui.pill>
                                @elseif($form->webhook_status === \App\Enums\WebhookStatus::Active)<x-ui.pill tone="ok">Active</x-ui.pill>
                                @else<x-ui.pill tone="bad">{{ $form->webhook_status->label() }}</x-ui.pill>@endif
                            </td>
                            <td class="tabular-nums">{{ $form->submissions_count }}</td>
                            <td class="text-em-soft">{{ $form->last_synced_at?->diffForHumans() ?? '–' }}</td>
                            <td class="text-right whitespace-nowrap space-x-3">
                                <a href="{{ route('admin.forms.mapping', $form) }}" wire:navigate class="text-em-accent hover:underline">Mapping →</a>
                                @if($form->isActive())
                                    <button type="button" wire:click="unlinkForm({{ $form->id }})" wire:confirm="Unlink {{ $form->title }}? The webhook is removed; its evaluations stay." class="text-em-soft hover:text-em-ink hover:underline">Unlink</button>
                                @endif
                                <button type="button" wire:click="deleteForm({{ $form->id }})" wire:confirm="Delete {{ $form->title }} and its {{ $form->submissions_count }} evaluations? This cannot be undone." class="text-em-bad hover:underline">Delete</button>
                            </td>
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

        <div class="panel p-6 space-y-3 max-w-2xl border-em-bad/40">
            <h2 class="text-sm font-semibold text-em-bad">Danger zone</h2>
            <p class="text-xs text-ink-soft">Deleting <strong>{{ $hospital->name }}</strong> removes its {{ $hospital->forms_count }} linked {{ Str::plural('form', $hospital->forms_count) }}, {{ number_format($hospital->submissions_count) }} {{ Str::plural('evaluation', $hospital->submissions_count) }} and {{ $hospital->users_count }} {{ Str::plural('user', $hospital->users_count) }}, and drops its Typeform webhooks. This cannot be undone.</p>
            <div>
                <label class="label" for="confirmDeleteName">Type the hospital name to confirm</label>
                <input id="confirmDeleteName" type="text" wire:model="confirmDeleteName" class="field" autocomplete="off" placeholder="{{ $hospital->name }}">
                <x-input-error :messages="$errors->get('confirmDeleteName')" class="mt-1" />
            </div>
            <div class="flex justify-end">
                <button type="button" wire:click="deleteHospital" wire:confirm="Delete {{ $hospital->name }} and all of its data?" class="btn-danger">Delete hospital</button>
            </div>
        </div>
    @endif
</x-ui.page>
