<div>
    <x-page-header title="Link a form" subtitle="Choose the Typeform survey that belongs to {{ $hospital->name }}." />
    <x-page>
        <x-flash />
        @if(! $httpsOk)
            <x-alert type="warning">Your <code>APP_URL</code> is not HTTPS. Typeform only delivers webhooks to public HTTPS URLs — use ngrok in local development and set <code>APP_URL</code> to the tunnel address.</x-alert>
        @endif

        @if($connection === null)
            <x-empty-state title="Typeform is not connected" message="Add a personal access token first.">
                <x-slot name="action"><a href="{{ route('admin.settings.typeform') }}" wire:navigate class="btn-primary">Connect Typeform</a></x-slot>
            </x-empty-state>
        @else
            @if($apiError)
                <x-alert type="danger">Could not load forms from Typeform: {{ $apiError }}</x-alert>
            @endif

            <div class="flex flex-wrap gap-3">
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search forms…" class="field max-w-sm" aria-label="Search forms">
                <select wire:model.live="workspace" class="field w-auto" aria-label="Workspace">
                    <option value="">All workspaces</option>
                    @foreach($workspaces as $ws)<option value="{{ $ws->id }}">{{ $ws->name }}</option>@endforeach
                </select>
                <span class="text-sm text-ink-soft self-center" wire:loading>Loading…</span>
            </div>

            @if($forms->isEmpty() && ! $apiError)
                <x-empty-state title="No forms available" message="All forms in this account are already linked, or none match your search." />
            @else
                <div class="panel overflow-x-auto">
                    <table class="table-base">
                        <thead><tr><th></th><th>Form</th><th>Workspace</th><th>Last updated</th></tr></thead>
                        <tbody>
                        @foreach($forms as $form)
                            <tr wire:key="tf-{{ $form->id }}" class="{{ $selected === $form->id ? 'bg-ink/5' : '' }}">
                                <td><input type="radio" name="selected" value="{{ $form->id }}" wire:click="select('{{ $form->id }}')" @checked($selected === $form->id) class="text-ink focus:ring-ink" aria-label="Select {{ $form->title }}"></td>
                                <td class="font-medium">{{ $form->title }} <span class="text-xs text-ink-soft font-mono">{{ $form->id }}</span></td>
                                <td>{{ $form->workspaceName ?? '—' }}</td>
                                <td class="text-ink-soft">{{ $form->lastUpdatedAt ? \Illuminate\Support\Carbon::parse($form->lastUpdatedAt)->diffForHumans() : '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @if($selectedForm)
                <div class="panel p-4 flex flex-wrap items-center justify-between gap-3 sticky bottom-4 shadow-lg">
                    <div>
                        <p class="text-sm">Link <strong>{{ $selectedForm->title }}</strong> to <strong>{{ $hospital->name }}</strong>?</p>
                        <p class="text-xs text-ink-soft">We will read its structure, register a webhook and import existing responses.</p>
                        <x-input-error :messages="$errors->get('selected')" class="mt-1" />
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="$set('selected', null)" class="btn-secondary">Cancel</button>
                        <button type="button" wire:click="link" wire:loading.attr="disabled" class="btn-primary">Link form</button>
                    </div>
                </div>
            @endif
        @endif
    </x-page>
</div>
