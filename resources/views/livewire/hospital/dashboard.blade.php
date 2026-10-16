<div>
    <x-page-header :title="$hospital->name.' · Dashboard'" :subtitle="'Evaluation scores for the '.$filters->label().($selectedForm ? ' · '.$selectedForm->title : ' · all surveys')">
        <x-slot name="actions">
            <a href="{{ $viewingAsAdmin ? route('hospitals.evaluations.index', [$hospital] + $this->filterQuery()) : route('evaluations.index', $this->filterQuery()) }}" wire:navigate class="btn-secondary">All evaluations</a>
        </x-slot>
    </x-page-header>

    <x-page>
        @if($viewingAsAdmin)
            <x-alert type="info">Viewing as admin. Hospital users see this same dashboard for {{ $hospital->name }} only.</x-alert>
        @endif

        <x-filters-bar :period="$period" :forms="$forms" :branches="$branches" :form="$form" :branch="$branch" :from="$from" :to="$to" />

        @php
            $ragColor = fn ($pct) => match (\App\Enums\Rag::fromPct($pct === null ? null : (float) $pct, $ragGreen, $ragAmber)) {
                \App\Enums\Rag::Green => 'text-rag-green', \App\Enums\Rag::Amber => 'text-rag-amber', \App\Enums\Rag::Red => 'text-rag-red', default => 'text-ink-soft' };
        @endphp

        @if($headline['count'] === 0 && $headline['previous_count'] === 0)
            <x-empty-state title="No evaluations yet" message="They appear here as soon as a survey is submitted. Try a longer period if you expect data." />
        @else
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-card title="Average score" class="md:col-span-1">
                    <p class="text-5xl font-semibold tabular-nums {{ $ragColor($headline['avg']) }}">{{ $headline['avg'] === null ? '–' : number_format($headline['avg'], 1).'%' }}</p>
                    <p class="text-sm text-ink-soft mt-1">
                        {{ $headline['count'] }} {{ Str::plural('evaluation', $headline['count']) }} in the {{ $filters->label() }}.
                        @if($headline['delta'] !== null)
                            <span class="{{ $headline['delta'] >= 0 ? 'text-rag-green' : 'text-rag-red' }} font-medium">{{ $headline['delta'] >= 0 ? '+' : '' }}{{ number_format($headline['delta'], 1) }} pts</span> vs previous {{ $filters->lengthInDays() }} days ({{ number_format($headline['previous_avg'], 1) }}%).
                        @elseif($headline['previous_count'] === 0)
                            No evaluations in the previous period.
                        @endif
                    </p>
                </x-card>
                <x-card title="Score distribution" class="md:col-span-1">
                    <dl class="grid grid-cols-3 gap-2 text-center">
                        @foreach(['green' => ['Green', 'text-rag-green'], 'amber' => ['Amber', 'text-rag-amber'], 'red' => ['Red', 'text-rag-red']] as $key => [$label, $cls])
                            <div class="rounded-md border border-line p-2">
                                <dt class="text-xs text-ink-soft">{{ $label }}</dt>
                                <dd class="text-2xl font-semibold tabular-nums {{ $cls }}">{{ $distribution[$key] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if($distribution['unscored'] > 0)<p class="text-xs text-ink-soft mt-2">{{ $distribution['unscored'] }} without a score (no scored questions answered).</p>@endif
                </x-card>
                <x-card title="Thresholds" class="md:col-span-1">
                    <p class="text-sm text-ink-soft">Green from <strong class="text-rag-green">{{ number_format($ragGreen, 0) }}%</strong>, amber from <strong class="text-rag-amber">{{ number_format($ragAmber, 0) }}%</strong>, red below.</p>
                    <p class="text-xs text-ink-soft mt-2">Scores are the weighted mean of answered sections, so skipped sections never pull a score down.</p>
                </x-card>
            </div>

            <x-card title="Score trend" :padding="true">
                <x-slot name="actions"><span class="text-xs text-ink-soft">average per {{ $trend['bucket'] }}</span></x-slot>
                @if(collect($trend['series'])->flatMap(fn ($s) => $s['data'])->filter(fn ($v) => $v !== null)->isEmpty())
                    <p class="text-sm text-ink-soft">No scored evaluations in this period.</p>
                @else
                    @php
                        $palette = ['#123A4A', '#2F855A', '#B7791F', '#B83A33', '#4E6875', '#6B46C1', '#A9B6BD'];
                        $datasets = collect($trend['series'])->values()->map(fn ($s, $i) => [
                            'label' => $s['label'], 'data' => $s['data'], 'borderColor' => $palette[$i % count($palette)], 'backgroundColor' => $palette[$i % count($palette)],
                            'spanGaps' => true, 'tension' => 0.25, 'pointRadius' => 3,
                        ])->all();
                    @endphp
                    <x-chart type="line" aria-label="Score trend" :config="[
                        'data' => ['labels' => $trend['labels'], 'datasets' => $datasets],
                        'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'scales' => ['y' => ['min' => 0, 'max' => 100, 'ticks' => ['callback' => null]]], 'plugins' => ['legend' => ['display' => count($datasets) > 1]]],
                    ]" />
                    <details class="mt-3 text-xs text-ink-soft">
                        <summary class="cursor-pointer">Show values</summary>
                        <table class="table-base mt-2"><thead><tr><th>Period</th>@foreach($trend['series'] as $s)<th>{{ $s['label'] }}</th>@endforeach</tr></thead>
                        <tbody>@foreach($trend['labels'] as $i => $label)<tr><td>{{ $label }}</td>@foreach($trend['series'] as $s)<td class="tabular-nums">{{ $s['data'][$i] === null ? '–' : number_format($s['data'][$i], 1).'% (n='.$s['counts'][$i].')' }}</td>@endforeach</tr>@endforeach</tbody></table>
                    </details>
                @endif
            </x-card>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <x-card title="Section averages">
                    @if($selectedForm === null)
                        <p class="text-sm text-ink-soft">Pick a survey above to see section scores — sections differ between surveys.</p>
                    @elseif(empty($sections))
                        <p class="text-sm text-ink-soft">No section scores in this period.</p>
                    @else
                        <ol class="space-y-2">
                            @foreach($sections as $s)
                                @php $rag = \App\Enums\Rag::fromPct($s['avg'], $ragGreen, $ragAmber); @endphp
                                <li>
                                    <div class="flex justify-between text-sm"><span class="font-medium">{{ $s['name'] }}</span><span class="tabular-nums {{ $ragColor($s['avg']) }}">{{ number_format($s['avg'], 1) }}% <span class="text-ink-soft text-xs">({{ $s['count'] }})</span></span></div>
                                    <div class="h-2 rounded bg-paper border border-line overflow-hidden" role="img" aria-label="{{ $s['name'] }} {{ number_format($s['avg'], 1) }} percent">
                                        <div class="h-full {{ match ($rag) { \App\Enums\Rag::Green => 'bg-rag-green', \App\Enums\Rag::Amber => 'bg-rag-amber', default => 'bg-rag-red' } }}" style="width: {{ $s['avg'] }}%"></div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>

                <x-card title="Weakest questions">
                    @if($selectedForm === null)
                        <p class="text-sm text-ink-soft">Pick a survey above to see the lowest-scoring questions.</p>
                    @elseif(empty($weakest))
                        <p class="text-sm text-ink-soft">No scored answers in this period.</p>
                    @else
                        <ol class="divide-y divide-line text-sm">
                            @foreach($weakest as $q)
                                <li class="py-2 flex justify-between gap-3">
                                    <span><span class="font-medium">{{ $q['title'] }}</span>@if($q['section'])<span class="block text-xs text-ink-soft">{{ $q['section'] }}</span>@endif</span>
                                    <span class="tabular-nums whitespace-nowrap {{ $ragColor($q['avg_pct']) }}">{{ number_format($q['avg_pct'], 1) }}% <span class="text-ink-soft text-xs">({{ $q['count'] }})</span></span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>

                <x-card title="Yes/No compliance">
                    @if($selectedForm === null)
                        <p class="text-sm text-ink-soft">Pick a survey above to see compliance per question.</p>
                    @elseif(empty($compliance))
                        <p class="text-sm text-ink-soft">No yes/no answers in this period.</p>
                    @else
                        <ul class="divide-y divide-line text-sm">
                            @foreach($compliance as $c)
                                <li class="py-2 flex justify-between gap-3">
                                    <span class="font-medium">{{ $c['title'] }}</span>
                                    <span class="tabular-nums whitespace-nowrap {{ $ragColor($c['pct_yes']) }}">{{ number_format($c['pct_yes'], 0) }}% yes <span class="text-ink-soft text-xs">({{ $c['yes'] }} / {{ $c['yes'] + $c['no'] }})</span></span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                <x-card title="Recent evaluations" :padding="false">
                    @if($recent->isEmpty())
                        <p class="p-4 text-sm text-ink-soft">No evaluations in this period.</p>
                    @else
                        <table class="table-base">
                            <thead><tr><th>Date</th><th>Survey</th><th>Facility</th><th>Score</th></tr></thead>
                            <tbody>
                            @foreach($recent as $e)
                                <tr wire:key="recent-{{ $e->id }}">
                                    <td class="whitespace-nowrap"><a href="{{ route('evaluations.show', $e) }}" wire:navigate class="underline">{{ $e->submitted_at?->format('d M Y') }}</a></td>
                                    <td class="text-ink-soft">{{ $e->form->title }}</td>
                                    <td>{{ $e->infoValue('facility') ?? '—' }}@if($e->infoValue('location'))<span class="text-xs text-ink-soft"> · {{ $e->infoValue('location') }}</span>@endif</td>
                                    <td><x-rag-pill :pct="$e->overall_pct" :rag="$e->rag" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </x-card>
            </div>
        @endif
    </x-page>
</div>
