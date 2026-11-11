<div>
    @php
        $e = $evaluation;
        $green = $e->form->settings->ragGreenMin; $amber = $e->form->settings->ragAmberMin;
        $band = fn ($pct) => \App\Support\ScoreBand::of($pct, $green, $amber);
        $overall = $band($e->overall_pct);
        $answered = $journeyScores->filter(fn ($s) => $s->pct !== null)->count();
        $circumference = 2 * M_PI * 52;
        $delta = $e->overall_pct !== null && $benchmark['overall_avg'] !== null ? round($e->overall_pct - $benchmark['overall_avg'], 1) : null;
        $facility = $e->infoValue('facility') ?? $e->branch?->name;
        $isComment = fn ($field) => \App\Livewire\Hospital\Evaluations\Show::isCommentField($field);
    @endphp

    <x-ui.page wide>
        <x-ui.header :title="$facility ?? $e->form->title" :back="$backUrl" back-label="Evaluations"
            :subtitle="$e->form->title.' · '.($e->submitted_at?->format('j M Y, H:i') ?? 'in progress').($e->infoValue('location') ? ' · '.$e->infoValue('location') : '').' · #'.$e->id">
            <x-slot name="actions">
                <button type="button" onclick="window.print()" class="btn-secondary">Print</button>
                @if(Route::has('evaluations.pdf'))<a href="{{ route('evaluations.pdf', $e) }}" class="btn-primary">Download PDF</a>@endif
            </x-slot>
        </x-ui.header>

        <x-ui.flash />

        {{-- Hero: score ring, comparison, visit facts --}}
        <section class="panel p-6 grid grid-cols-1 lg:grid-cols-[auto_minmax(0,1fr)_minmax(0,1.2fr)] gap-x-10 gap-y-6 items-center">
            <div class="relative h-[132px] w-[132px] shrink-0" role="img" aria-label="Overall score {{ $e->overall_pct === null ? 'not available' : number_format($e->overall_pct, 1).' percent, '.$overall->label() }}">
                <svg viewBox="0 0 120 120" class="h-full w-full -rotate-90">
                    <circle cx="60" cy="60" r="52" fill="none" stroke="#F3F1F0" stroke-width="9"/>
                    <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $overall->hex() }}" stroke-width="9" stroke-linecap="round"
                            stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - min(100, max(0, (float) ($e->overall_pct ?? 0))) / 100) }}"/>
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center gap-1">
                    <span class="text-[28px] font-semibold tracking-[-0.02em] leading-none">{{ $e->overall_pct === null ? '–' : number_format($e->overall_pct, 1) }}<span class="text-xs font-normal text-em-mute">%</span></span>
                    <x-ui.pill :tone="$overall->tone()" class="text-[10.5px]">{{ $overall->label() }}</x-ui.pill>
                </div>
            </div>

            <div class="flex flex-col gap-1.5 min-w-0">
                <div class="eyebrow">Overall score</div>
                @if($delta !== null)
                    <div class="text-[15px] font-semibold {{ $delta > 0 ? 'text-em-ok' : ($delta < 0 ? 'text-em-bad' : 'text-em-soft') }}"><span aria-hidden="true">{{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '▶') }}</span> {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1) }} pts vs survey average ({{ number_format($benchmark['overall_avg'], 1) }}%)</div>
                @endif
                @if($benchmark['percentile'] !== null)
                    <div class="text-em-soft">Better than {{ $benchmark['percentile'] }}% of {{ $benchmark['count'] }} visits in the last 12 months</div>
                @endif
                <div class="text-em-soft">{{ $e->points !== null ? number_format($e->points, 0).' / '.number_format($e->max_points, 0).' points' : 'No scored answers' }} · {{ $answered }} of {{ $journeyScores->count() }} touchpoints answered</div>
                @if($isAdmin || $e->is_partial)
                    <div class="pt-1 flex flex-wrap gap-1.5">
                        @if($isAdmin)<x-badge :variant="match ($e->status) { \App\Enums\SubmissionStatus::Approved => 'green', \App\Enums\SubmissionStatus::PendingReview => 'amber', default => 'red' }">{{ $e->status->label() }}</x-badge>@endif
                        @if($e->is_partial)<x-ui.pill tone="warn">Partial</x-ui.pill>@endif
                    </div>
                @endif
            </div>

            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-4">
                @foreach(\App\Enums\InfoKey::cases() as $key)
                    @php $val = $key === \App\Enums\InfoKey::Branch && $e->branch ? $e->branch->name : $e->infoValue($key->value); @endphp
                    @if($val !== null && $key->isDisplayInfo())
                        <div class="min-w-0"><dt class="eyebrow">{{ $key->label() }}</dt><dd class="font-medium truncate mt-0.5" title="{{ $val }}">{{ $val }}</dd></div>
                    @endif
                @endforeach
                <div><dt class="eyebrow">Submitted</dt><dd class="font-medium mt-0.5">{{ $e->submitted_at?->format('j M Y, H:i') ?? 'In progress (partial)' }}</dd></div>
                @if($isAdmin)
                    <div><dt class="eyebrow">Mapping version</dt><dd class="font-medium mt-0.5"><a href="{{ route('admin.forms.mapping', $e->form) }}?version={{ $e->formVersion->version_number }}" class="text-em-accent hover:underline">v{{ $e->formVersion->version_number }}</a></dd></div>
                @endif
            </dl>
        </section>

        {{-- Patient journey --}}
        @if($journeyScores->isNotEmpty())
            <x-ui.card title="Patient journey" subtitle="Score per touchpoint, in visit order · dashed line = survey average · click a touchpoint to open it" :divider="false">
                <div class="px-[22px] pt-3 pb-5 overflow-x-auto">
                    <div class="grid gap-2 min-w-max sm:min-w-0" style="grid-template-columns: repeat({{ $journeyScores->count() }}, minmax(5.5rem, 1fr));" role="list" aria-label="Touchpoint scores">
                        @foreach($journeyScores as $score)
                            @php $pct = $score->pct; $sb = $band($pct); $avg = $benchmark['sections'][$score->section_name] ?? null; @endphp
                            <a href="#section-{{ $score->section_id }}" role="listitem" class="group flex flex-col items-center gap-2 text-center"
                               x-on:click.prevent="document.getElementById('section-{{ $score->section_id }}')?.dispatchEvent(new CustomEvent('open-section')); document.getElementById('section-{{ $score->section_id }}')?.scrollIntoView({behavior: 'smooth', block: 'start'})"
                               title="{{ $score->section_name }}: {{ $pct === null ? 'not answered' : number_format((float) $pct, 1).'%' }}{{ $avg !== null ? ' · survey average '.number_format($avg, 1).'%' : '' }}">
                                <span class="text-[13px] font-semibold tabular-nums {{ $sb->text() }}">{{ $pct === null ? '–' : number_format((float) $pct, 0).'%' }}</span>
                                <span class="relative w-full h-[110px] rounded-lg bg-[#F8F7F6] overflow-hidden group-hover:ring-1 group-hover:ring-em-field" aria-hidden="true">
                                    @if($pct !== null)
                                        <span class="absolute bottom-0 inset-x-0 {{ $sb->tint() }}" style="height: {{ max(3, (float) $pct) }}%"><span class="absolute top-0 inset-x-0 h-[3px] {{ $sb->bar() }}"></span></span>
                                    @endif
                                    @if($avg !== null)
                                        <span class="absolute inset-x-0 border-t border-dashed border-em-soft/60" style="bottom: {{ $avg }}%"></span>
                                    @endif
                                </span>
                                <span class="text-[11.5px] leading-tight text-em-soft line-clamp-2">{{ $score->section_name }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </x-ui.card>
        @endif

        {{-- Touchpoints --}}
        <div class="flex flex-col gap-3">
            @foreach($sections as $block)
                @php
                    $section = $block['section']; $score = $block['score']; $pct = $score?->pct; $sb = $band($pct);
                    $avg = $benchmark['sections'][$section->name] ?? null;
                    $rows = collect($block['rows']);
                    $comments = $rows->filter(fn ($r) => $isComment($r['field']));
                    $questions = $rows->reject(fn ($r) => $isComment($r['field']));
                @endphp
                <section id="section-{{ $section->id }}" class="panel scroll-mt-4" x-data="{ open: {{ $editing || ($loop->index < 2 && $pct !== null) ? 'true' : 'false' }} }" x-on:open-section="open = true" x-on:open-all-sections.window="open = true">
                    <button type="button" x-on:click="open = !open" class="w-full flex items-center gap-4 px-[22px] py-[18px] text-left rounded-2xl" :aria-expanded="open.toString()">
                        <span class="h-8 w-[3px] rounded-full {{ $sb->bar() }} shrink-0" aria-hidden="true"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-semibold truncate">{{ $section->name }}</span>
                            <span class="block text-xs text-em-soft">{{ $questions->count() }} {{ Str::plural('question', $questions->count()) }}@if($score && $pct !== null) · {{ number_format($score->points, 0) }} / {{ number_format($score->max_points, 0) }} points @endif @if($avg !== null && $pct !== null) · survey avg {{ number_format($avg, 0) }}% @endif</span>
                        </span>
                        @if($pct !== null)
                            <x-ui.pill :tone="$sb->tone()" class="tabular-nums">{{ number_format((float) $pct, 0) }}%</x-ui.pill>
                        @else
                            <span class="text-xs text-em-mute">{{ $rows->isEmpty() ? 'not answered' : 'not scored' }}</span>
                        @endif
                        <span aria-hidden="true" x-text="open ? '−' : '+'" class="w-4 text-center text-em-mute"></span>
                    </button>
                    <div x-show="open" x-collapse class="border-t border-em-line">
                        @if($rows->isEmpty())
                            <p class="px-[22px] py-3.5 text-em-soft">No answers in this touchpoint.</p>
                        @else
                            <dl class="px-[22px] divide-y divide-[#F3F1F0]">
                                @foreach($questions as $row)
                                    @php $field = $row['field']; $answer = $row['answer']; $scored = $answer->isScored(); $ratio = $scored && $answer->max_points > 0 ? 100 * $answer->points / $answer->max_points : null; $rb = $band($ratio); @endphp
                                    <div class="py-3 grid grid-cols-1 sm:grid-cols-[minmax(0,1fr)_11rem_5rem] gap-2 sm:gap-6 items-center {{ $scored ? '' : 'text-em-soft' }}">
                                        <dt>{{ $field->title }}@if(isset($edits[$answer->typeform_field_id])) <x-ui.pill tone="info" class="ml-1 align-middle text-[10.5px]" title="Edited by eMotion{{ isset($edits[$answer->typeform_field_id]['original_points']) ? ' · original '.number_format((float) $edits[$answer->typeform_field_id]['original_points'], 0).' points' : '' }}">edited</x-ui.pill>@endif</dt>
                                        @if($editing && $ratio !== null)
                                            <dd class="sm:col-span-2 flex items-center sm:justify-end gap-2">
                                                <input type="number" step="0.5" min="0" max="{{ $answer->max_points }}" wire:model="editPoints.{{ $answer->typeform_field_id }}" class="field w-24 text-right tabular-nums" aria-label="Points for {{ $field->title }}">
                                                <span class="text-em-soft whitespace-nowrap">/ {{ number_format($answer->max_points, 0) }} points</span>
                                                <x-input-error :messages="$errors->get('editPoints.'.$answer->typeform_field_id)" />
                                            </dd>
                                        @elseif($answer->answer_type === 'file_url')
                                            <dd class="sm:col-span-2 sm:text-right">
                                                @php $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $answer->value_text); @endphp
                                                @if($isImage)
                                                    <a href="{{ route('evaluations.files.show', [$e, $answer->id]) }}" target="_blank" rel="noopener" class="inline-block"><img src="{{ route('evaluations.files.show', [$e, $answer->id]) }}" alt="{{ $answer->value_text }}" class="max-h-40 rounded-xl border border-em-line" loading="lazy"></a>
                                                @else
                                                    <a href="{{ route('evaluations.files.show', [$e, $answer->id]) }}" class="text-em-accent hover:underline">Download {{ $answer->value_text }}</a>
                                                @endif
                                            </dd>
                                        @elseif($ratio !== null)
                                            <dd aria-hidden="true"><span class="block h-1.5 rounded-full bg-[#F3F1F0] overflow-hidden"><span class="block h-full rounded-full {{ $rb->bar() }}" style="width: {{ max(2, $ratio) }}%"></span></span></dd>
                                            <dd class="sm:text-right font-semibold tabular-nums {{ $rb->text() }}" title="{{ number_format($answer->points, 0) }} / {{ number_format($answer->max_points, 0) }} points">
                                                @if($field->isScale()){{ $answer->value_text }} / {{ $field->scale_max }}@else{{ $answer->value_text ?? '—' }}@endif
                                            </dd>
                                        @else
                                            <dd class="sm:col-span-2 sm:text-right {{ $field->type === 'long_text' ? 'whitespace-pre-wrap text-left sm:text-left' : '' }}">{{ $answer->value_text ?? '—' }}</dd>
                                        @endif
                                    </div>
                                @endforeach
                                @foreach($comments as $row)
                                    <div class="py-3 flex flex-col sm:flex-row gap-1 sm:gap-4">
                                        <dt class="text-em-soft shrink-0">{{ $comments->count() > 1 ? $row['field']->title : 'Shopper comment' }}@if(isset($edits[$row['answer']->typeform_field_id])) <x-ui.pill tone="info" class="ml-1 align-middle text-[10.5px]" title="Edited by eMotion">edited</x-ui.pill>@endif</dt>
                                        @if($editing)
                                            <dd class="flex-1">
                                                <textarea rows="3" wire:model="editText.{{ $row['answer']->typeform_field_id }}" class="field w-full" aria-label="{{ $row['field']->title }}"></textarea>
                                                <x-input-error :messages="$errors->get('editText.'.$row['answer']->typeform_field_id)" class="mt-1" />
                                            </dd>
                                        @else
                                            <dd class="italic whitespace-pre-wrap">{{ trim((string) $row['answer']->value_text) !== '' ? '“'.$row['answer']->value_text.'”' : '—' }}</dd>
                                        @endif
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </section>
            @endforeach

            @if($orphanAnswers->isNotEmpty())
                <section class="panel">
                    <h2 class="px-[22px] py-3.5 text-sm font-semibold text-em-soft">Other answers <span class="text-xs font-normal">(questions not in this mapping version)</span></h2>
                    <dl class="px-[22px] divide-y divide-[#F3F1F0] border-t border-em-line">
                        @foreach($orphanAnswers as $answer)
                            <div class="py-3 text-em-soft grid grid-cols-1 sm:grid-cols-[1fr_2fr] gap-2"><dt class="font-mono text-xs">{{ $answer->typeform_field_id }}</dt><dd>{{ $answer->value_text }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        @if($supportingDocs->isNotEmpty() || $isAdmin)
            <x-ui.card title="Supporting documents" subtitle="Evidence added by eMotion: SMS confirmations, invoices, photos" :divider="false">
                <div class="px-[22px] pt-1 pb-5 space-y-3">
                    @if($supportingDocs->isEmpty())
                        <p class="text-[13px] text-em-soft">No documents added yet.</p>
                    @else
                        <ul class="divide-y divide-[#F3F1F0]">
                            @foreach($supportingDocs as $doc)
                                <li class="py-2.5 flex items-center gap-3" wire:key="doc-{{ $doc->id }}">
                                    @if($doc->isImage())
                                        <a href="{{ route('evaluations.attachments.show', [$e, $doc->id]) }}" target="_blank" rel="noopener" class="shrink-0"><img src="{{ route('evaluations.attachments.show', [$e, $doc->id]) }}" alt="" class="h-12 w-12 object-cover rounded-lg border border-em-line" loading="lazy"></a>
                                    @else
                                        <span class="h-12 w-12 rounded-lg border border-em-line bg-em-bg flex items-center justify-center text-[10px] font-semibold text-em-soft uppercase shrink-0">{{ Str::limit(pathinfo($doc->original_name, PATHINFO_EXTENSION), 4, '') ?: 'file' }}</span>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <a href="{{ route('evaluations.attachments.show', [$e, $doc->id]) }}" target="_blank" rel="noopener" class="font-medium hover:text-em-accent truncate block">{{ $doc->original_name }}</a>
                                        <p class="text-xs text-em-soft">{{ $doc->size ? number_format($doc->size / 1024, 0).' KB · ' : '' }}added {{ $doc->created_at?->format('j M Y') }}@if($doc->uploader) by {{ $doc->uploader->name }}@endif</p>
                                    </div>
                                    @if($isAdmin)
                                        <button type="button" wire:click="deleteDocument({{ $doc->id }})" wire:confirm="Remove {{ $doc->original_name }}?" class="text-xs text-em-bad hover:underline no-print">Remove</button>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if($isAdmin)
                        <form wire:submit="uploadDocuments" class="no-print flex flex-wrap items-end gap-2 pt-2 border-t border-em-line">
                            <div class="flex-1 min-w-[14rem]">
                                <label class="label" for="documents">Add documents</label>
                                <input id="documents" type="file" wire:model="documents" multiple class="block text-sm text-ink-soft" accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                                <x-input-error :messages="$errors->get('documents')" class="mt-1" />
                                @foreach($errors->get('documents.*') as $messages)<x-input-error :messages="$messages" class="mt-1" />@endforeach
                                <p class="text-xs text-em-soft mt-1" wire:loading wire:target="documents">Uploading…</p>
                            </div>
                            <button type="submit" class="btn-secondary" @disabled($documents === [])>Upload</button>
                        </form>
                    @endif
                </div>
            </x-ui.card>
        @endif

        @if($isAdmin)
            <x-ui.card title="Admin tools" class="no-print">
                <div class="px-[22px] py-4">
                    @if($editing)
                        <div class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border border-em-accent/40 bg-em-accent-soft/40 px-4 py-3">
                            <p class="flex-1 text-[13px]">Editing: change points on scored questions and the shopper comments above, then save. Every change is logged with the original value.</p>
                            <button type="button" wire:click="saveEdits" class="btn-primary">Save changes</button>
                            <button type="button" wire:click="cancelEditing" class="btn-secondary">Cancel</button>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-2 items-end">
                        <div class="flex-1 min-w-[14rem]">
                            <label class="label" for="reviewNote">Review note (optional)</label>
                            <input id="reviewNote" type="text" wire:model="reviewNote" class="field" placeholder="Why approved / rejected">
                        </div>
                        @if($e->status !== \App\Enums\SubmissionStatus::Approved)
                            <button type="button" wire:click="approve" class="btn-primary">Approve &amp; publish</button>
                        @endif
                        @if($e->status !== \App\Enums\SubmissionStatus::Rejected)
                            <button type="button" wire:click="reject" wire:confirm="Reject this evaluation? Hospital users will not see it." class="btn-danger">Reject</button>
                        @endif
                        <button type="button" wire:click="rescore" wire:confirm="Re-score this evaluation against mapping v{{ $e->formVersion->version_number }}?" class="btn-secondary">Re-score this evaluation</button>
                        @unless($editing)
                            <button type="button" wire:click="startEditing" class="btn-secondary">Edit evaluation</button>
                        @endunless
                    </div>
                    @if($e->reviewed_at)
                        <p class="text-xs text-em-soft mt-3">Reviewed {{ $e->reviewed_at->diffForHumans() }} by {{ $e->reviewer?->name ?? 'unknown' }}@if($e->review_note): “{{ $e->review_note }}”@endif</p>
                    @endif
                    <details class="mt-4">
                        <summary class="cursor-pointer text-[12.5px] text-em-accent">View raw payload</summary>
                        <pre class="mt-2 text-xs bg-em-bg border border-em-line rounded-xl p-3 overflow-x-auto max-h-96">{{ $rawJson }}</pre>
                    </details>
                </div>
            </x-ui.card>
        @endif
    </x-ui.page>
</div>
