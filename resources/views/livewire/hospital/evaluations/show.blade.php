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
        $delta = $e->overall_pct !== null && $benchmark['overall_avg'] !== null ? round($e->overall_pct - $benchmark['overall_avg'], 1) : null;
        $answeredScores = $e->sectionScores->filter(fn ($s) => $s->pct !== null)->values();
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
            <div class="relative p-5 sm:p-6 grid grid-cols-1 lg:grid-cols-2 gap-6 items-center">
                <div class="flex items-center gap-5 min-w-0">
                    <div class="relative h-36 w-36 shrink-0" role="img" aria-label="Overall score {{ $e->overall_pct === null ? 'not available' : number_format($e->overall_pct, 1).' percent' }}">
                        <svg viewBox="0 0 120 120" class="h-36 w-36 -rotate-90">
                            <circle cx="60" cy="60" r="52" fill="none" stroke="#EEF2F5" stroke-width="10"/>
                            @if($benchmark['overall_avg'] !== null)
                                <circle cx="60" cy="60" r="52" fill="none" stroke="#A9B6BD" stroke-width="2" stroke-dasharray="2 {{ $circumference - 2 }}" stroke-dashoffset="{{ -$circumference * $benchmark['overall_avg'] / 100 }}" />
                            @endif
                            <circle cx="60" cy="60" r="52" fill="none" stroke="{{ $ragStroke($e->overall_pct) }}" stroke-width="10" stroke-linecap="round"
                                    stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $circumference * (1 - min(100, max(0, (float) ($e->overall_pct ?? 0))) / 100) }}"/>
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-3xl font-semibold tracking-tight {{ $ragText($e->overall_pct) }}">{{ $e->overall_pct === null ? '–' : number_format($e->overall_pct, 1) }}<span class="text-base">%</span></span>
                            <span class="text-[11px] uppercase tracking-wider text-ink-soft">{{ $ragOf($e->overall_pct)?->label() ?? 'no score' }}</span>
                        </div>
                    </div>
                    <div class="space-y-1.5 min-w-0">
                        <div class="eyebrow">Overall score</div>
                        @if($delta !== null)
                            <div class="text-sm font-medium {{ $delta > 0 ? 'text-rag-green' : ($delta < 0 ? 'text-rag-red' : 'text-ink-soft') }}"><span aria-hidden="true">{{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '▶') }}</span> {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1) }} pts vs survey average ({{ number_format($benchmark['overall_avg'], 1) }}%)</div>
                        @endif
                        @if($benchmark['percentile'] !== null)
                            <div class="text-sm text-ink-soft">Better than {{ $benchmark['percentile'] }}% of {{ $benchmark['count'] }} evaluations in the last 12 months</div>
                        @endif
                        <div class="text-sm text-ink-soft">{{ $e->points !== null ? number_format($e->points, 0).' / '.number_format($e->max_points, 0).' points' : 'No scored answers' }} · {{ $answered }} of {{ $e->sectionScores->count() }} sections answered</div>
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

        {{-- Visit KPIs --}}
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
            <x-kpi label="Scored items" :value="$insights['scored_count']" :note="$insights['unscored'].' informational answers'" />
            <x-kpi label="Full marks" :value="$insights['full']" tone="green" :note="$insights['scored_count'] > 0 ? number_format(100 * $insights['full'] / $insights['scored_count'], 0).'% of scored items' : null" />
            <x-kpi label="Partial" :value="$insights['partial']" tone="amber" note="scored between 0 and max" />
            <x-kpi label="Missed" :value="$insights['zero']" :tone="$insights['zero'] > 0 ? 'red' : null" :note="$insights['zero'] > 0 ? 'zero points — see attention points' : 'no zero-point answers'" />
            <x-kpi label="Visit duration" :value="$insights['duration_minutes'] === null ? '–' : $insights['duration_minutes']" :unit="$insights['duration_minutes'] === null ? null : 'min'" note="from opening to submitting the survey" />
            <x-kpi label="Comments" :value="count($insights['comments'])" note="free-text remarks by the shopper" />
        </div>

        {{-- Journey --}}
        <x-card title="Journey" subtitle="Score per section in form order — click a tile to open the section">
            <x-journey-strip :scores="$e->sectionScores" :green="$green" :amber="$amber" />
        </x-card>

        {{-- Radar · vs average · answer outcomes --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <x-card title="Section profile" :subtitle="'This visit vs the survey average'.($benchmark['count'] > 0 ? ' ('.$benchmark['count'].' evaluations, 12 months)' : '')" class="xl:col-span-5">
                @if($answeredScores->count() < 3)
                    <p class="text-sm text-ink-soft">The radar needs at least three answered sections.</p>
                @else
                    @php
                        $labels = $answeredScores->map(fn ($s) => $s->section_name)->values()->all();
                        $visit = $answeredScores->map(fn ($s) => round((float) $s->pct, 1))->values()->all();
                        $avg = $answeredScores->map(fn ($s) => $benchmark['sections'][$s->section_name] ?? null)->values()->all();
                        $hasAvg = collect($avg)->filter(fn ($v) => $v !== null)->isNotEmpty();
                        $datasets = [[
                            'label' => 'This visit', 'data' => $visit, 'borderColor' => '#2457F5', 'backgroundColor' => 'rgba(36, 87, 245, 0.18)', 'borderWidth' => 2,
                            'pointBackgroundColor' => '#2457F5', 'pointBorderColor' => '#ffffff', 'pointBorderWidth' => 2, 'pointRadius' => 4,
                        ]];
                        if ($hasAvg) {
                            $datasets[] = ['label' => 'Survey average', 'data' => $avg, 'borderColor' => '#4E6875', 'backgroundColor' => 'rgba(78, 104, 117, 0.06)', 'borderWidth' => 2, 'borderDash' => [5, 4], 'pointBackgroundColor' => '#4E6875', 'pointBorderColor' => '#ffffff', 'pointBorderWidth' => 2, 'pointRadius' => 3];
                        }
                    @endphp
                    <x-chart type="radar" height="h-80" aria-label="Section profile radar" :config="[
                        'data' => ['labels' => $labels, 'datasets' => $datasets],
                        'options' => [
                            'responsive' => true, 'maintainAspectRatio' => false,
                            'scales' => ['r' => ['min' => 0, 'max' => 100, 'ticks' => ['stepSize' => 25, 'backdropColor' => 'transparent', 'color' => '#4E6875', 'font' => ['size' => 10]], 'grid' => ['color' => 'rgba(220,227,231,0.9)'], 'angleLines' => ['color' => 'rgba(220,227,231,0.9)'], 'pointLabels' => ['color' => '#123A4A', 'font' => ['size' => 11]]]],
                            'plugins' => ['legend' => ['display' => $hasAvg, 'position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8, 'color' => '#123A4A']]],
                        ],
                    ]" />
                @endif
            </x-card>

            <x-card title="Above / below the survey average" subtitle="Percentage points per section — green is better than average" class="xl:col-span-4">
                @if($answeredScores->isEmpty() || empty($benchmark['sections']))
                    <p class="text-sm text-ink-soft">No comparison available yet.</p>
                @else
                    <ol class="space-y-2.5">
                        @foreach($answeredScores as $s)
                            @php $a = $benchmark['sections'][$s->section_name] ?? null; $d = $a === null ? null : round((float) $s->pct - $a, 1); $w = $d === null ? 0 : min(50, abs($d) * 2); @endphp
                            <li class="text-sm">
                                <div class="flex justify-between gap-2 mb-1"><span class="truncate" title="{{ $s->section_name }}">{{ $s->section_name }}</span><span class="tabular-nums font-medium whitespace-nowrap {{ $d === null ? 'text-ink-soft' : ($d > 0 ? 'text-rag-green' : ($d < 0 ? 'text-rag-red' : 'text-ink-soft')) }}">{{ $d === null ? 'no average' : ($d > 0 ? '+' : '').number_format($d, 1) }}</span></div>
                                <div class="relative h-2 rounded-full bg-paper overflow-hidden" role="img" aria-label="{{ $s->section_name }} {{ $d === null ? 'no comparison' : ($d > 0 ? '+' : '').number_format($d, 1).' points vs average' }}">
                                    <span class="absolute inset-y-0 left-1/2 w-px bg-line" aria-hidden="true"></span>
                                    @if($d !== null && $d !== 0.0)
                                        <span class="absolute inset-y-0 rounded-full {{ $d > 0 ? 'bg-rag-green left-1/2' : 'bg-rag-red right-1/2' }}" style="width: {{ $w }}%"></span>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>

            <x-card title="Answer outcomes" subtitle="Scored items in this visit" class="xl:col-span-3">
                @if($insights['scored_count'] === 0)
                    <p class="text-sm text-ink-soft">No scored answers.</p>
                @else
                    <x-chart type="doughnut" height="h-44" aria-label="Answer outcomes" :center-text="['value' => (string) $insights['scored_count'], 'label' => 'scored']" :config="[
                        'data' => ['labels' => ['Full marks', 'Partial', 'Missed'], 'datasets' => [['data' => [$insights['full'], $insights['partial'], $insights['zero']], 'backgroundColor' => ['#2F855A', '#B7791F', '#B83A33'], 'borderColor' => '#ffffff', 'borderWidth' => 2, 'hoverOffset' => 4]]],
                        'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'cutout' => '72%', 'plugins' => ['legend' => ['display' => false]]],
                    ]" />
                    <ul class="mt-3 space-y-1.5 text-sm">
                        @foreach([['Full marks', 'bg-rag-green', $insights['full']], ['Partial', 'bg-rag-amber', $insights['partial']], ['Missed', 'bg-rag-red', $insights['zero']]] as [$label, $cls, $n])
                            <li class="flex items-center justify-between gap-2"><span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $cls }}" aria-hidden="true"></span>{{ $label }}</span><span class="tabular-nums text-ink-soft">{{ $n }} · {{ number_format(100 * $n / $insights['scored_count'], 0) }}%</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Highlights · attention · comments --}}
        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
            <x-card title="Highlights" subtitle="Best-scored answers" class="xl:col-span-4">
                @if($insights['best'] === [])
                    <p class="text-sm text-ink-soft">No scored answers.</p>
                @else
                    <ul class="divide-y divide-line/70 text-sm">
                        @foreach($insights['best'] as $a)
                            <li class="py-2 flex items-start justify-between gap-3">
                                <span class="min-w-0"><span class="block font-medium">{{ $a['title'] }}</span><span class="block text-xs text-ink-soft">{{ $a['section'] }} · answered “{{ $a['value'] }}”</span></span>
                                <span class="shrink-0 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ring-1 ring-inset text-rag-green ring-rag-green/30 bg-rag-green/5">{{ number_format($a['points'], 0) }} / {{ number_format($a['max'], 0) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Attention points" subtitle="Lowest-scored answers" class="xl:col-span-4">
                @if($insights['worst'] === [])
                    <p class="text-sm text-ink-soft">No scored answers.</p>
                @else
                    <ul class="divide-y divide-line/70 text-sm">
                        @foreach($insights['worst'] as $a)
                            <li class="py-2 flex items-start justify-between gap-3">
                                <span class="min-w-0"><span class="block font-medium">{{ $a['title'] }}</span><span class="block text-xs text-ink-soft">{{ $a['section'] }} · answered “{{ $a['value'] }}”</span></span>
                                <span class="shrink-0 inline-flex rounded-full px-2 py-0.5 text-xs font-semibold tabular-nums ring-1 ring-inset {{ $a['ratio'] <= 0.001 ? 'text-rag-red ring-rag-red/30 bg-rag-red/5' : 'text-rag-amber ring-rag-amber/30 bg-rag-amber/5' }}">{{ number_format($a['points'], 0) }} / {{ number_format($a['max'], 0) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Shopper comments" subtitle="Free-text remarks, by section" class="xl:col-span-4">
                @if($insights['comments'] === [])
                    <p class="text-sm text-ink-soft">No comments were left.</p>
                @else
                    <ul class="space-y-3">
                        @foreach($insights['comments'] as $c)
                            <li class="relative pl-3 border-l-2 border-accent/40 text-sm">
                                <span class="block text-xs text-ink-soft mb-0.5">{{ $c['section'] }}</span>
                                <span class="block whitespace-pre-wrap">“{{ $c['text'] }}”</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        {{-- Sections --}}
        <div class="space-y-3">
            @foreach($sections as $block)
                @php
                    $section = $block['section']; $score = $block['score']; $pct = $score?->pct;
                    $scoredRows = array_values(array_filter($block['rows'], fn ($r) => $r['answer']->isScored() && $r['answer']->max_points > 0));
                @endphp
                <section id="section-{{ $section->id }}" class="panel scroll-mt-4" x-data="{ open: {{ $pct !== null ? 'true' : 'false' }} }" x-on:open-section="open = true">
                    <button type="button" x-on:click="open = !open" class="w-full flex items-center gap-4 px-5 py-3.5 text-left rounded-2xl" :aria-expanded="open.toString()">
                        <span class="h-9 w-1.5 rounded-full {{ $ragBg($pct) }} shrink-0" aria-hidden="true"></span>
                        <span class="flex-1 min-w-0">
                            <span class="block font-semibold truncate">{{ $section->name }}</span>
                            <span class="block text-xs text-ink-soft">{{ count($block['rows']) }} {{ Str::plural('answer', count($block['rows'])) }}@if($score && $pct !== null) · {{ number_format($score->points, 0) }} / {{ number_format($score->max_points, 0) }} points @endif@if(isset($benchmark['sections'][$section->name]) && $pct !== null) · avg {{ number_format($benchmark['sections'][$section->name], 0) }}% @endif</span>
                        </span>
                        @if($scoredRows !== [])
                            <span class="hidden md:flex items-center gap-0.5" aria-label="Answer scores in order" title="Each square is one scored answer">
                                @foreach($scoredRows as $r)
                                    @php $ratio = 100 * $r['answer']->points / $r['answer']->max_points; @endphp
                                    <span class="h-3 w-3 rounded-sm {{ $ragBg($ratio) }}" style="opacity: {{ 0.45 + 0.55 * $ratio / 100 }}" title="{{ $r['field']->title }}: {{ number_format($r['answer']->points, 0) }} / {{ number_format($r['answer']->max_points, 0) }}"></span>
                                @endforeach
                            </span>
                        @endif
                        @if($pct !== null)
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
                                    <div class="px-5 py-3 grid grid-cols-1 sm:grid-cols-[minmax(0,2fr)_minmax(0,3fr)_auto] gap-2 sm:gap-4 items-center text-sm {{ $scored ? '' : 'text-ink-soft' }}">
                                        <dt class="{{ $scored ? 'font-medium text-ink' : '' }}">{{ $field->title }}</dt>
                                        <dd class="{{ $field->type === 'long_text' ? 'whitespace-pre-wrap' : '' }}">
                                            @if($answer->answer_type === 'file_url')
                                                @php $isImage = (bool) preg_match('/\.(jpe?g|png|gif|webp)$/i', (string) $answer->value_text); @endphp
                                                @if($isImage)
                                                    <a href="{{ route('evaluations.files.show', [$e, $answer->id]) }}" target="_blank" rel="noopener"><img src="{{ route('evaluations.files.show', [$e, $answer->id]) }}" alt="{{ $answer->value_text }}" class="max-h-48 rounded-xl ring-1 ring-line" loading="lazy"></a>
                                                @else
                                                    <a href="{{ route('evaluations.files.show', [$e, $answer->id]) }}" class="underline">Download {{ $answer->value_text }}</a>
                                                @endif
                                            @elseif($scored && $ratio !== null && $field->isScale())
                                                <span class="flex items-center gap-3">
                                                    <span class="flex-1 max-w-[12rem] h-1.5 rounded-full bg-paper overflow-hidden" aria-hidden="true"><span class="block h-full rounded-full {{ $ragBg($ratio) }}" style="width: {{ $ratio }}%"></span></span>
                                                    <span class="tabular-nums">{{ $answer->value_text }}<span class="text-ink-soft text-xs"> / {{ $field->scale_max }}</span></span>
                                                </span>
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
