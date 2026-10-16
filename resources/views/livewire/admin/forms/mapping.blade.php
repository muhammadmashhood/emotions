<div x-data="mappingEditor()" x-init="init()">
    <x-page-header :title="'Mapping · '.$form->title" :subtitle="$readOnly ? 'Version '.$version->version_number.' (read-only)' : 'Version '.$version->version_number.' (current)'">
        <x-slot name="actions">
            <label class="sr-only" for="version-select">Version</label>
            <select id="version-select" class="field w-auto" onchange="window.location = '{{ route('admin.forms.mapping', $form) }}?version=' + this.value">
                @foreach($versions as $v)
                    <option value="{{ $v->version_number }}" @selected($v->id === $version->id)>Version {{ $v->version_number }} · {{ $v->created_at->format('d M Y') }}{{ $v->id === $form->current_version_id ? ' (current)' : '' }}</option>
                @endforeach
            </select>
            <a href="{{ route('admin.forms.show', $form) }}" wire:navigate class="btn-secondary">Form details</a>
        </x-slot>
    </x-page-header>

    <x-page class="pb-28">
        <x-flash />
        @if($readOnly)
            <x-alert type="info">You are viewing an older version. Only the current version can be edited.</x-alert>
        @elseif($version->needs_review)
            <x-alert type="warning">This version needs review: check that every question sits in the right section and that scored flags are correct, then save.</x-alert>
        @endif

        @error('fields')<x-alert type="danger">{{ $message }}</x-alert>@enderror

        @if($rescoreCount)
            <x-alert type="info">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <span>Recalculate scores for {{ $rescoreCount }} {{ Str::plural('evaluation', $rescoreCount) }} on this version?</span>
                    <span class="flex gap-2">
                        <button type="button" wire:click="dismissRescore" class="btn-secondary">Not now</button>
                        <button type="button" wire:click="rescore" class="btn-primary">Recalculate</button>
                    </span>
                </div>
            </x-alert>
        @endif

        <div class="space-y-4">
            @foreach($sections as $i => $section)
                <section class="panel" wire:key="section-{{ $section['key'] }}">
                    <header class="flex flex-wrap items-center gap-3 px-4 py-3 border-b border-line bg-paper/60 rounded-t-lg">
                        <span class="text-xs font-semibold text-ink-soft tabular-nums w-6">{{ $i + 1 }}</span>
                        <div class="flex-1 min-w-[12rem]">
                            <label class="sr-only" for="section-name-{{ $section['key'] }}">Section name</label>
                            <input id="section-name-{{ $section['key'] }}" type="text" wire:model.blur="sections.{{ $i }}.name" class="field font-medium" @disabled($readOnly)>
                            <x-input-error :messages="$errors->get('sections.'.$i.'.name')" class="mt-1" />
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-xs text-ink-soft" for="section-weight-{{ $section['key'] }}">Weight</label>
                            <input id="section-weight-{{ $section['key'] }}" type="number" min="0" max="10" step="0.5" wire:model.blur="sections.{{ $i }}.weight" class="field w-20" @disabled($readOnly)>
                            <x-input-error :messages="$errors->get('sections.'.$i.'.weight')" />
                        </div>
                        @unless($readOnly)
                            <div class="flex items-center gap-1">
                                <button type="button" wire:click="moveSection({{ $i }}, -1)" class="btn-secondary px-2" aria-label="Move section up" @disabled($i === 0)>↑</button>
                                <button type="button" wire:click="moveSection({{ $i }}, 1)" class="btn-secondary px-2" aria-label="Move section down" @disabled($i === count($sections) - 1)>↓</button>
                                <button type="button" wire:click="deleteSection({{ $i }})" class="btn-secondary px-2 text-rag-red" aria-label="Delete section" @disabled(count($section['fields']) > 0) title="{{ count($section['fields']) > 0 ? 'Move the questions out first' : 'Delete section' }}">✕</button>
                            </div>
                        @endunless
                    </header>

                    <ul class="divide-y divide-line min-h-[2.5rem]" data-section="{{ $section['key'] }}" x-ref="list-{{ $section['key'] }}" data-sortable>
                        @forelse($section['fields'] as $fieldId)
                            @php($field = $fields[$fieldId])
                            <li class="flex flex-wrap items-center gap-3 px-4 py-2 {{ $fieldState[$fieldId]['is_scored'] ? '' : 'text-ink-soft' }}" data-field="{{ $fieldId }}" wire:key="field-{{ $fieldId }}">
                                @unless($readOnly)<span class="drag-handle cursor-grab text-ink-soft select-none" title="Drag to move" aria-hidden="true">⋮⋮</span>@endunless
                                <div class="flex-1 min-w-[14rem]">
                                    <div class="text-sm font-medium text-ink">{{ $field->title }} @if($field->is_new_in_version)<x-badge variant="amber">New</x-badge>@endif</div>
                                    <div class="text-xs text-ink-soft flex gap-2">
                                        <x-badge>{{ str_replace('_', ' ', $field->type) }}</x-badge>
                                        @if($field->scaleLabel())<span>{{ $field->scaleLabel() }}</span>@endif
                                        @if($field->description)<span class="truncate max-w-xs" title="{{ $field->description }}">{{ $field->description }}</span>@endif
                                    </div>
                                </div>
                                <label class="flex items-center gap-1 text-xs" title="{{ $scorable[$fieldId] ? '' : 'This question type cannot be scored' }}">
                                    <input type="checkbox" wire:click="toggleScored({{ $fieldId }})" @checked($fieldState[$fieldId]['is_scored']) @disabled($readOnly || ! $scorable[$fieldId]) class="rounded border-line text-ink focus:ring-ink">
                                    Scored
                                </label>
                                <div>
                                    <label class="sr-only" for="info-{{ $fieldId }}">Info</label>
                                    <select id="info-{{ $fieldId }}" wire:model="fieldState.{{ $fieldId }}.info_key" class="field py-1 text-xs w-auto" @disabled($readOnly)>
                                        <option value="">Info: none</option>
                                        @foreach($infoKeys as $key)<option value="{{ $key->value }}">Info: {{ $key->label() }}</option>@endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('fields.'.$fieldId.'.info_key')" />
                                </div>
                                @unless($readOnly)
                                    <div>
                                        <label class="sr-only" for="move-{{ $fieldId }}">Move to</label>
                                        <select id="move-{{ $fieldId }}" class="field py-1 text-xs w-auto" wire:change="moveField({{ $fieldId }}, $event.target.value)">
                                            <option value="{{ $section['key'] }}">Move to…</option>
                                            @foreach($sections as $target)
                                                @if($target['key'] !== $section['key'])<option value="{{ $target['key'] }}">{{ $target['name'] }}</option>@endif
                                            @endforeach
                                        </select>
                                    </div>
                                @endunless
                            </li>
                        @empty
                            <li class="px-4 py-3 text-xs text-ink-soft italic" data-empty>No questions — drag some here or delete this section.</li>
                        @endforelse
                    </ul>
                </section>
            @endforeach
        </div>

        @unless($readOnly)
            <button type="button" wire:click="addSection" class="btn-secondary">Add section</button>

            <div class="fixed bottom-0 inset-x-0 bg-white border-t border-line no-print">
                <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap items-center justify-between gap-3">
                    <p class="text-sm text-ink-soft">Changes apply to new evaluations immediately. You can recalculate existing ones after saving.</p>
                    <button type="button" wire:click="save" wire:loading.attr="disabled" class="btn-primary">Save mapping</button>
                </div>
            </div>
        @endunless
    </x-page>

    <script>
        function mappingEditor() {
            return {
                sortables: [],
                init() {
                    this.setup();
                    // Re-attach after Livewire re-renders (lists are re-created).
                    Livewire.hook('morph.updated', () => this.setup());
                },
                setup() {
                    if (@json($readOnly) || ! window.Sortable) return;
                    this.$root.querySelectorAll('[data-sortable]').forEach((list) => {
                        if (list.__sortable) return;
                        list.__sortable = window.Sortable.create(list, {
                            group: 'fields',
                            handle: '.drag-handle',
                            animation: 120,
                            draggable: '[data-field]',
                            onEnd: (evt) => {
                                const fieldId = parseInt(evt.item.dataset.field, 10);
                                const toKey = evt.to.dataset.section;
                                const newIndex = Array.from(evt.to.querySelectorAll('[data-field]')).indexOf(evt.item);
                                // Revert the DOM move; Livewire re-renders from the new server state.
                                const from = evt.from;
                                const ref = from.querySelectorAll('[data-field]')[evt.oldIndex] || null;
                                from.insertBefore(evt.item, ref);
                                this.$wire.moveField(fieldId, toKey, newIndex);
                            },
                        });
                    });
                },
            };
        }
    </script>
</div>
