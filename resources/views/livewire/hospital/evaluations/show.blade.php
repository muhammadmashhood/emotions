<div>
    @php
        $e = $evaluation;
        $green = $e->form->settings->ragGreenMin; $amber = $e->form->settings->ragAmberMin;
        $ragOf = fn ($pct) => \App\Enums\Rag::fromPct($pct === null ? null : (float) $pct, $green, $amber);
        $ragText = fn ($pct) => match ($ragOf($pct)) { \App\Enums\Rag::Green => 'text-rag-green', \App\Enums\Rag::Amber => 'text-rag-amber', \App\Enums\Rag::Red => 'text-rag-red', default => 'text-ink-soft' };
        $ragStroke = fn ($pct) => match ($ragOf($pct)) { \App\Enums\Rag::Green => '#2F855A', \App\Enums\Rag::Amber => '#B7791F', \App\Enums\Rag::Red => '#B83A33', default => '#A9B6BD' };
        $ragBg = fn ($pct) => match ($ragOf($pct)) { \App\Enums\Rag::Green => 'bg-rag-green', \App\Enums\Rag::Amber => 'bg-rag-amber', \App\Enums\Rag::Red => 'bg-rag-red', default => 'bg-rag-neutral/40' };
        $answered = $e->sectionScores->filter(fn ($s) => $s->pct !== null)->count();
        $circumference = 2 * M_PI * 52;
    @endphp

    <x-page-header :title="($e->infoValue('facility') ?? $e->form->title)" :subtitle="$e->form->title.' · '.($e->submitted_at?->format('d M Y, H:i') ?? 'in progress').($e->infoValue('location') ? ' · '.$e->infoValue('location') : '')">
        <x-slot name="actions">
            <a href="{{ $backUrl }}" wire:navigate class="btn-secondary">Back to list</a>
            <button type="button" onclick="window.print()" class="btn-secondary">Print</button>
            @if(Route::has('evaluations.pdf'))<a href="{{ route('evaluations.pdf', $e) }}" class="btn-primary">Download PDF</a>@endif
        </x-slot>
    </x-page-header>

    <x-page>
        <x-flash />

        {{-- Hero --}}
        <div class="panel relative overflow-hidden">
            <div class="absolute inset-y-0 right-0 w-1/2 bg-gradient-to-l from-accent-soft/70 to-transparent pointer-events-none" aria-hidden="true"></div>
            <div class="relative p-5 sm:p-6 grid grid-cols-1 lg:grid-cols-[auto_1fr] gap-6 items-center">
                <div class="flex items-center gap-5">
                    <div class="relative h-32 w-32 shrink-0" role="img" aria-label="Overall score {{ $e->overall_pct === null ? 'not available' : number_format($e->overall_pct, 1).' percent' }}">
                        <svg viewBox="0 0 120 120" class="h-32 w-32 -rotate-90">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="#EEF2F5" stroke-width="10"/>
                            <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $ragStroke($e->overall_pct) }}" stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - min(100, max(0, (float) ($e->overall_pct ?? 0))) / 100) }}"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-3xl font-semibold tracking-tight {{ $ragText($e->overall_pct) }}">{{ $e->overall_pct === null ? '–' : number_format($e->overall_pct, 1) }}<span class="text-base">%</span></span>
                            <span class="text-[11px] uppercase tracking-wider text-ink-soft">{{ $ragOf($e->overall_pct)?->label() ?? 'no score' }}</span>
                        </div>
                    </div>
                    <div class="space-y-1">
                        <div class="eyebrow">Overall score</div>
                        <div class="text-sm text-ink-soft">{{ $e->points !== null ? number_format($e->points, 0).' / '.number_format($e->max_points, 0).' points' : 'No scored answers' }}</div>
                        <div class="text-sm text-ink-soft">{{ $answered }} of {{ $e->sectionScores->count() }} sections answered</div>
                        @if($isAdmin)
                            <div class="pt-1 flex flex-wrap gap-1.5">
                                <x-badge :variant="match ($e->status) { \App\Enums\SubmissionStatus::Approved => 'green', \App\Enums\SubmissionStatus::PendingReview => 'amber', default => 'red' }">{{ $e->status->label() }}</x-badge>
                                @if($e->is_partial)<x-badge variant="amber">Partial</x-badge>@endif
                                <x-badge>{{ $e->source->value }}</x-badge>
                            </div>
                        @endif
                    </div>
                </div>

                <dl class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                    @foreach(\App\Enums\InfoKey::cases() as $key)
                        @php $val = $key === \App\Enums\InfoKey::Branch && $e->branch ? $e->branch->name : $e->infoValue($key->value); @endphp
                        @if($val !== null)
                            <div class="min-w-0"><dt class="eyebrow">{{ $key->label() }}</dt><dd class="font-medium truncate" title="{{ $val }}">{{ $val }}</dd></div>
                        @endif
                    @endforeach
                    <div><dt class="eyebrow">Submitted</dt><dd class="font-medium">{{ $e->submitted_at?->format('d M Y H:i') ?? 'In progress (partial)' }}</dd></div>
                    @if($isAdmin)
                        <div><dt class="eyebrow">Mapping version</dt><dd class="font-medium"><a href="{{ route('admin.forms.mapping', $e->form) }}?version={{ $e->formVersion->version_number }}" class="underline decoration-line hover:decoration-ink">v{{ $e->formVersion->version_number }}</a></dd></div>
                    @endif
                </dl>
            </div>
        </div>

        <x-card title="Journey" subtitle="Score per section in form order — click a tile to open the section">
            <x-journey-strip :scores="$e->sectionScores" :green="$green" :amber="$amber" />
        </x-card>

        <div class="space-y-3">
            @foreach($sections as $block)
                @php $section = $block['section']; $score = $block['score']; $pct = $score?->pct; @endphp
                <section id="section-{{ $section->id }}" class="panel scroll-mt-4" x-data="{ open: {{ $pct !== null ? 'true' : 'false' }} }" x-on:open-section="open = true">
                    <button type="button" x-on:click="open = !open" class="w-full flex items-center gap-4 px-5 py-3.5 text-left rounded-2xl" :aria-expanded="open.toString()">
                        <span class="h-9 w-1.5 rounded-full {{ $ragBg($pct) }} shrink-0" aria-hidden="true"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-semibold truncate">{{ $section->name }}</span>
                            <span class="block text-xs text-ink-soft">{{ count($block['rows']) }} {{ Str::plural('answer', count($block['rows'])) }}@if($score && $pct !== null) · {{ number_format($score->points, 0) }} / {{ number_format($score->max_points, 0) }} points @endif</span>
                        </span>
                        @if($pct !== null)
                            <span class="hidden sm:block w-32 h-2 rounded-full bg-paper overflow-hidden" aria-hidden="true"><span class="block h-full rounded-full {{ $ragBg($pct) }}" style="width: {{ $pct }}%"></span></span>
                            <x-rag-pill :pct="$pct" />
                        @else
                            <span class="text-xs text-ink-soft">not answered</span>
                        @endif
                        <span aria-hidden="true" x-text="open ? '−' : '+'" class="w-4 text-center text-ink-soft"></span>
                    </button>
                    <div x-show="open" x-collapse class="border-t border-line/70">
                        @if(empty($block['rows']))
                            <p class="px-5 py-3 text-sm text-ink-soft">No answers in this section.</p>
                        @else
                            <dl class="divide-y divide-line/60">
                                @foreach($block['rows'] as $row)
                                    @php $field = $row['field']; $answer = $row['answer']; $scored = $answer->isScored(); $ratio = $scored && $answer->max_points > 0 ? 100 * $answer->points / $answer->max_points : null; @endphp
                                    <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto] gap-2 sm:gap-4 items-start text-sm {{ $scored ? '' : 'text-ink-soft' }}">
                                        <dt class="{{ $scored ? 'font-medium text-ink' : '' }}">{{ $field->title }}</dt>
                                        <dd class="{{ $field->type === 'long_text' ? 'whitespace-pre-wrap' : '' }}">
                                            @if($answer->answer_type === 'file_url')
                                                @php $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $answer->value_text); @endphp
                                                @if($isImage)
                                                    <a href="{{ route('evaluations.files.show', [$e, $answer->id]) }}" target="_blank" rel="noopener"><img src="{{ route('evaluations.files.show', [$e, $answer->id]) }}" alt="{{ $answer->value_text }}" class="max-h-48 rounded-xl ring-1 ring-line" loading="lazy"></a>
                                                @else
                                                    <a href="{{ route('evaluations.files.show', [$e, $answer->id]) }}" class="underline">Download {{ $answer->value_text }}</a>
                                                @endif
                                            @else
                                                {{ $answer->value_text ?? '—' }}
                                            @endif
                                        </dd>
                                        <dd class="whitespace-nowrap sm:text-right">
                                            @if($scored)
                                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ring-1 ring-inset {{ match ($ragOf($ratio)) { \App\Enums\Rag::Green => 'text-rag-green ring-rag-green/30 bg-rag-green/5', \App\Enums\Rag::Amber => 'text-rag-amber ring-rag-amber/30 bg-rag-amber/5', default => 'text-rag-red ring-rag-red/30 bg-rag-red/5' } }}">{{ number_format($answer->points, 0) }} / {{ number_format($answer->max_points, 0) }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        @endif
                    </div>
                </section>
            @endforeach

            @if($orphanAnswers->isNotEmpty())
                <section class="panel">
                    <h2 class="px-5 py-3 font-semibold text-ink-soft">Other answers <span class="text-xs font-normal">(questions not in this mapping version)</span></h2>
                    <dl class="divide-y divide-line/60 border-t border-line/70">
                        @foreach($orphanAnswers as $answer)
                            <div class="px-5 py-3 text-sm text-ink-soft grid grid-cols-1 sm:grid-cols-[1fr_2fr] gap-2"><dt class="font-mono text-xs">{{ $answer->typeform_field_id }}</dt><dd>{{ $answer->value_text }}</dd></div>
                        @endforeach
                    </dl>
                </section>
            @endif
        </div>

        @if($isAdmin)
            <x-card title="Admin tools" class="no-print">
                <div class="flex flex-wrap gap-2 items-start">
                    <button type="button" wire:click="rescore" wire:confirm="Re-score this evaluation against mapping v{{ $e->formVersion->version_number }}?" class="btn-secondary">Re-score this evaluation</button>
                    @if($e->status !== \App\Enums\SubmissionStatus::Approved)
                        <button type="button" wire:click="approve" class="btn-primary">Approve</button>
                    @endif
                    @if($e->status !== \App\Enums\SubmissionStatus::Rejected)
                        <button type="button" wire:click="reject" wire:confirm="Reject this evaluation? Hospital users will not see it." class="btn-danger">Reject</button>
                    @endif
                    <div class="flex-1 min-w-[14rem]">
                        <label class="label" for="reviewNote">Review note (optional)</label>
                        <input id="reviewNote" type="text" wire:model="reviewNote" class="field" placeholder="Why approved / rejected">
                    </div>
                </div>
                @if($e->reviewed_at)
                    <p class="text-xs text-ink-soft mt-3">Reviewed {{ $e->reviewed_at->diffForHumans() }} by {{ $e->reviewer?->name ?? 'unknown' }}@if($e->review_note): “{{ $e->review_note }}”@endif</p>
                @endif
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm underline">View raw payload</summary>
                    <pre class="mt-2 text-xs bg-paper border border-line rounded-xl p-3 overflow-x-auto max-h-96">{{ $rawJson }}</pre>
                </details>
            </x-card>
        @endif
    </x-page>
</div>
