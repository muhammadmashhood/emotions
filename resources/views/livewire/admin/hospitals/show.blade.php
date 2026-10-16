<div>
    <x-page-header :title="$hospital->name" :subtitle="$hospital->is_active ? 'Active hospital account' : 'Inactive — its users cannot log in'">
        <x-slot name="actions">
            @if(Route::has('hospitals.dashboard'))<a href="{{ route('hospitals.dashboard', $hospital) }}" wire:navigate class="btn-secondary">Open dashboard</a>@endif
            @if(Route::has('admin.hospitals.forms.link'))<a href="{{ route('admin.hospitals.forms.link', $hospital) }}" wire:navigate class="btn-primary">Link a form</a>@endif
        </x-slot>
    </x-page-header>

    <x-page>
        <x-flash />
        <x-tabs :tabs="['overview' => 'Overview', 'forms' => 'Forms', 'users' => 'Users', 'settings' => 'Settings']" :current="$tab" />

        @if($tab === 'overview')
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <x-card title="Active forms"><p class="text-3xl font-semibold tabular-nums">{{ $hospital->forms_count }}</p></x-card>
                <x-card title="Users"><p class="text-3xl font-semibold tabular-nums">{{ $hospital->users_count }}</p></x-card>
                <x-card title="Evaluations"><p class="text-3xl font-semibold tabular-nums">{{ $hospital->submissions_count }}</p></x-card>
            </div>
            @if(\Illuminate\Support\Facades\Route::has('hospitals.dashboard'))
                <livewire:hospital.mini-dashboard :hospital="$hospital" wire:key="mini-{{ $hospital->id }}" />
            @endif
        @elseif($tab === 'forms')
            @if($forms->isEmpty())
                <x-empty-state title="No forms linked" message="Link a Typeform survey to start receiving evaluations for this hospital.">
                    <x-slot name="action">@if(Route::has('admin.hospitals.forms.link'))<a href="{{ route('admin.hospitals.forms.link', $hospital) }}" wire:navigate class="btn-primary">Link a form</a>@endif</x-slot>
                </x-empty-state>
            @else
                <div class="panel overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th>Form</th><th>Status</th><th>Version</th><th>Webhook</th><th>Evaluations</th><th>Last sync</th><th></th></tr></thead>
                        <tbody>
                        @foreach($forms as $form)
                            <tr wire:key="f-{{ $form->id }}">
                                <td class="font-medium"><a href="{{ route('admin.forms.show', $form) }}" wire:navigate class="hover:underline">{{ $form->title }}</a><div class="text-xs text-ink-soft">{{ $form->typeform_form_id }}</div></td>
                                <td>@if($form->isActive())<x-badge variant="green">Linked</x-badge>@else<x-badge>Unlinked</x-badge>@endif</td>
                                <td>v{{ $form->currentVersion?->version_number ?? '–' }} @if($form->currentVersion?->needs_review)<x-badge variant="amber">Needs review</x-badge>@endif</td>
                                <td><x-badge :variant="$form->webhook_status === \App\Enums\WebhookStatus::Active ? 'green' : ($form->isActive() ? 'red' : 'neutral')">{{ $form->webhook_status->label() }}</x-badge></td>
                                <td class="tabular-nums">{{ $form->submissions_count }}</td>
                                <td class="text-ink-soft">{{ $form->last_synced_at?->diffForHumans() ?? '–' }}</td>
                                <td class="text-right"><a href="{{ route('admin.forms.mapping', $form) }}" wire:navigate class="text-sm underline text-ink-soft hover:text-ink">Mapping</a></td>
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
    </x-page>
</div>
