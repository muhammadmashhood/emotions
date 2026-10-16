<div>
    <x-page-header :title="$form->title" :subtitle="$form->hospital->name.' · Typeform '.$form->typeform_form_id">
        <x-slot name="actions">
            <a href="{{ route('admin.hospitals.show', ['hospital' => $form->hospital, 'tab' => 'forms']) }}" wire:navigate class="btn-secondary">Back to hospital</a>
            <a href="{{ route('admin.forms.mapping', $form) }}" wire:navigate class="btn-primary">Edit mapping</a>
        </x-slot>
    </x-page-header>

    <x-page>
        <x-flash />
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <x-card title="Status">@if($form->isActive())<x-badge variant="green">Linked</x-badge>@else<x-badge>Unlinked</x-badge>@endif</x-card>
                    <x-card title="Version"><span class="text-2xl font-semibold">v{{ $form->currentVersion?->version_number ?? '–' }}</span> @if($form->currentVersion?->needs_review)<x-badge variant="amber">Needs review</x-badge>@endif</x-card>
                    <x-card title="Evaluations"><span class="text-2xl font-semibold tabular-nums">{{ $submissionCount }}</span></x-card>
                    <x-card title="Last evaluation"><span class="text-sm">{{ $lastSubmission ? \Illuminate\Support\Carbon::parse($lastSubmission)->diffForHumans() : '—' }}</span></x-card>
                </div>

                <x-card title="Webhook">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div><dt class="text-ink-soft">Status</dt><dd><x-badge :variant="$form->webhook_status === \App\Enums\WebhookStatus::Active ? 'green' : ($form->isActive() ? 'red' : 'neutral')">{{ $form->webhook_status->label() }}</x-badge></dd></div>
                        <div><dt class="text-ink-soft">Last checked</dt><dd>{{ $form->webhook_checked_at?->diffForHumans() ?? 'never' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-ink-soft">Endpoint</dt><dd class="font-mono text-xs break-all">{{ $form->webhookUrl() }}</dd></div>
                        @if($form->webhook_last_error)<div class="sm:col-span-2"><dt class="text-ink-soft">Last error</dt><dd class="text-rag-red text-xs">{{ $form->webhook_last_error }}</dd></div>@endif
                    </dl>
                    @if(! $httpsOk)<x-alert type="warning" class="mt-3">APP_URL is not HTTPS; Typeform will not deliver to this endpoint.</x-alert>@endif
                    @if($form->isActive())
                        <div class="mt-4 flex flex-wrap gap-2">
                            <button type="button" wire:click="repairWebhook" wire:loading.attr="disabled" class="btn-secondary">Repair webhook</button>
                            <button type="button" wire:click="resync" class="btn-secondary">Re-sync responses</button>
                        </div>
                    @endif
                </x-card>

                <x-card title="Versions" :padding="false">
                    <table class="table-base">
                        <thead><tr><th>Version</th><th>Created</th><th>Questions</th><th>Evaluations</th><th>Review</th><th></th></tr></thead>
                        <tbody>
                        @foreach($form->versions as $version)
                            <tr wire:key="v-{{ $version->id }}">
                                <td class="font-medium">v{{ $version->version_number }} @if($version->id === $form->current_version_id)<x-badge variant="ink">current</x-badge>@endif</td>
                                <td class="text-ink-soft">{{ $version->created_at->format('d M Y H:i') }}</td>
                                <td class="tabular-nums">{{ $version->fields_count }}</td>
                                <td class="tabular-nums">{{ $version->submissions_count }}</td>
                                <td>@if($version->needs_review)<x-badge variant="amber">Needs review</x-badge>@else<x-badge variant="green">Reviewed</x-badge>@endif</td>
                                <td class="text-right"><a href="{{ route('admin.forms.mapping', $form) }}?version={{ $version->version_number }}" wire:navigate class="text-sm underline">{{ $version->id === $form->current_version_id ? 'Edit mapping' : 'View mapping' }}</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </x-card>
            </div>

            <div class="space-y-6">
                <form wire:submit="saveSettings" class="panel p-4 space-y-4">
                    <h2 class="text-sm font-semibold">Scoring settings</h2>
                    <div>
                        <label class="label" for="rag_green_min">Green from (%)</label>
                        <input id="rag_green_min" type="number" step="0.5" min="0" max="100" wire:model="rag_green_min" class="field">
                        <x-input-error :messages="$errors->get('rag_green_min')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="rag_amber_min">Amber from (%)</label>
                        <input id="rag_amber_min" type="number" step="0.5" min="0" max="100" wire:model="rag_amber_min" class="field">
                        <x-input-error :messages="$errors->get('rag_amber_min')" class="mt-1" />
                    </div>
                    <div>
                        <label class="label" for="yes_no_points">Points for a "Yes"</label>
                        <input id="yes_no_points" type="number" step="1" min="1" max="100" wire:model="yes_no_points" class="field">
                        <x-input-error :messages="$errors->get('yes_no_points')" class="mt-1" />
                    </div>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" wire:model="include_partial" class="mt-0.5 rounded border-line text-ink focus:ring-ink">
                        <span>Show partial (unfinished) evaluations to hospital users</span>
                    </label>
                    <div class="border-t border-line pt-3">
                        <label class="label" for="branchSourceType">Branch source</label>
                        <select id="branchSourceType" wire:model.live="branchSourceType" class="field">
                            <option value="none">None (no branch filter)</option>
                            <option value="hidden">Hidden field in the Typeform link</option>
                            <option value="field">A question in the survey</option>
                        </select>
                        <x-input-error :messages="$errors->get('branchSourceType')" class="mt-1" />
                        @if($branchSourceType === 'hidden')
                            <label class="label mt-2" for="branchHiddenKey">Hidden field key</label>
                            <input id="branchHiddenKey" type="text" wire:model="branchHiddenKey" class="field" placeholder="branch">
                            <p class="text-xs text-ink-soft mt-1">Share the survey as <code>…/to/{{ $form->typeform_form_id }}#{{ $branchHiddenKey ?: 'branch' }}=North+wing</code>.</p>
                            <x-input-error :messages="$errors->get('branchHiddenKey')" class="mt-1" />
                        @elseif($branchSourceType === 'field')
                            <label class="label mt-2" for="branchFieldId">Question</label>
                            <select id="branchFieldId" wire:model="branchFieldId" class="field">
                                <option value="">— choose —</option>
                                @foreach($branchFields as $bf)<option value="{{ $bf->typeform_field_id }}">{{ $bf->title }} ({{ str_replace('_', ' ', $bf->type) }})</option>@endforeach
                            </select>
                            <p class="text-xs text-ink-soft mt-1">A dropdown works best; free text spellings create separate branches.</p>
                            <x-input-error :messages="$errors->get('branchFieldId')" class="mt-1" />
                        @endif
                    </div>
                    <button type="submit" class="btn-primary w-full">Save settings</button>
                </form>

                <div class="panel p-4 space-y-3">
                    <h2 class="text-sm font-semibold">Recalculate scores</h2>
                    <p class="text-xs text-ink-soft">Re-scores every evaluation of this form from its stored answers.</p>
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" wire:model="useLatestMapping" class="mt-0.5 rounded border-line text-ink focus:ring-ink">
                        <span>Use the current mapping for all evaluations (also moves them to version v{{ $form->currentVersion?->version_number }})</span>
                    </label>
                    <button type="button" wire:click="rescore" wire:confirm="Recalculate scores for {{ $submissionCount }} evaluations?" class="btn-secondary w-full">Recalculate scores</button>
                </div>

                @if($form->isActive())
                    <div class="panel p-4 space-y-3 border-rag-red/40">
                        <h2 class="text-sm font-semibold text-rag-red">Unlink form</h2>
                        <p class="text-xs text-ink-soft">Removes the webhook. Existing evaluations are kept and stay visible. You can re-link later.</p>
                        <button type="button" wire:click="unlink" wire:confirm="Unlink {{ $form->title }} from {{ $form->hospital->name }}?" class="btn-danger w-full">Unlink</button>
                    </div>
                @endif
            </div>
        </div>
    </x-page>
</div>
