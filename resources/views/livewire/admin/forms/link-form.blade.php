<x-ui.page>
    <x-ui.header title="Link a Typeform" :back="route('admin.hospitals.show', $hospital)" :back-label="$hospital->name"
        subtitle="New responses arrive by webhook. Linking reads the form structure, registers the webhook and imports existing responses." />

    <x-ui.flash />

    {{-- Stepper --}}
    <ol class="flex items-center gap-3 text-[13px]" aria-label="Steps">
        <li class="flex items-center gap-2 {{ $selectedForm ? 'text-em-soft' : 'font-semibold' }}">
            <span class="h-6 w-6 rounded-full flex items-center justify-center text-[11px] font-semibold bg-em-ink text-white">{{ $selectedForm ? '✓' : '1' }}</span>
            Typeform survey
        </li>
        <li class="h-px w-10 bg-em-field" aria-hidden="true"></li>
        <li class="flex items-center gap-2 {{ $selectedForm ? 'font-semibold' : 'text-em-soft' }}" @if($selectedForm) aria-current="step" @endif>
            <span class="h-6 w-6 rounded-full flex items-center justify-center text-[11px] font-semibold {{ $selectedForm ? 'bg-em-ink text-white' : 'border border-em-field text-em-soft' }}">2</span>
            Confirm
        </li>
    </ol>

    @if(! $httpsOk)
        <div class="rounded-xl border border-em-warn/20 bg-em-warn-bg px-4 py-2.5 text-[13px] text-em-warn" role="alert">Your <code>APP_URL</code> is not HTTPS. Typeform only delivers webhooks to public HTTPS URLs. Use ngrok in local development and set <code>APP_URL</code> to the tunnel address.</div>
    @endif

    @if($connection === null)
        <div class="bg-white border border-em-line rounded-2xl p-10 text-center max-w-[860px]">
            <p class="font-medium">Typeform is not connected</p>
            <p class="text-[13px] text-em-soft mt-1">Add a personal access token first.</p>
            <a href="{{ route('admin.settings.typeform') }}" wire:navigate class="btn btn-primary mt-4">Connect Typeform</a>
        </div>
    @else
        <section class="bg-white border border-em-line rounded-2xl p-[22px] flex flex-col gap-3 max-w-[860px]">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold">Choose the Typeform survey</h2>
                    <p class="text-xs text-em-soft">From the connected Typeform account · surveys already linked are hidden</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <select wire:model.live="workspace" class="field w-auto" aria-label="Workspace">
                        <option value="">All workspaces</option>
                        @foreach($workspaces as $ws)<option value="{{ $ws->id }}">{{ $ws->name }}</option>@endforeach
                    </select>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search surveys…" class="field w-56" aria-label="Search forms">
                </div>
            </div>

            @if($apiError)
                <div class="rounded-xl border border-em-bad/20 bg-em-bad-bg px-4 py-2.5 text-[13px] text-em-bad" role="alert">Could not load forms from Typeform: {{ $apiError }}</div>
            @endif
            <p class="text-xs text-em-mute" wire:loading>Loading…</p>

            @if($forms->isEmpty() && ! $apiError)
                <p class="py-6 text-center text-[13px] text-em-soft">No forms available. All forms in this account are already linked, or none match your search.</p>
            @else
                <div class="flex flex-col gap-2" role="radiogroup" aria-label="Typeform surveys">
                    @foreach($forms as $form)
                        <label wire:key="tf-{{ $form->id }}" class="flex items-center gap-3.5 px-4 py-3.5 rounded-xl border cursor-pointer transition-colors {{ $selected === $form->id ? 'border-em-ink bg-[#FCFBFA]' : 'border-em-line hover:border-em-field' }}">
                            <input type="radio" name="selected" value="{{ $form->id }}" wire:click="select('{{ $form->id }}')" @checked($selected === $form->id) class="h-4 w-4 border-em-hover text-em-ink focus:ring-em-accent" aria-label="Select {{ $form->title }}">
                            <span class="flex-1 min-w-0">
                                <span class="block font-medium truncate">{{ $form->title }}</span>
                                <span class="block text-xs text-em-soft">Typeform <span class="font-mono">{{ $form->id }}</span>@if($form->workspaceName) · {{ $form->workspaceName }}@endif</span>
                            </span>
                            <span class="text-xs text-em-soft whitespace-nowrap">{{ $form->lastUpdatedAt ? 'Edited '.\Illuminate\Support\Carbon::parse($form->lastUpdatedAt)->diffForHumans() : '' }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </section>

        @if($selectedForm)
            <div class="bg-white border border-em-line rounded-2xl p-[18px] flex flex-wrap items-center justify-between gap-3 max-w-[860px] sticky bottom-4 shadow-[0_16px_40px_-20px_rgba(43,31,38,0.35)]">
                <div>
                    <p class="text-[13px]">Link <strong>{{ $selectedForm->title }}</strong> to <strong>{{ $hospital->name }}</strong>?</p>
                    <p class="text-xs text-em-soft">We will read its structure, register a webhook and import existing responses.</p>
                    <x-input-error :messages="$errors->get('selected')" class="mt-1" />
                </div>
                <div class="flex gap-2">
                    <button type="button" wire:click="$set('selected', null)" class="btn btn-secondary">Back</button>
                    <button type="button" wire:click="link" wire:loading.attr="disabled" class="btn btn-primary">Link form</button>
                </div>
            </div>
        @endif
    @endif
</x-ui.page>
