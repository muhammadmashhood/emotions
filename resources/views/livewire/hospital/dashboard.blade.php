<div>
    @php
        $band = fn ($pct) => \App\Support\ScoreBand::of($pct, $ragGreen, $ragAmber);
        $hasData = $headline['count'] > 0 || $headline['previous_count'] > 0;
        $scored = $distribution['green'] + $distribution['amber'] + $distribution['red'];
        $listUrl = $viewingAsAdmin ? route('hospitals.evaluations.index', [$hospital] + $this->filterQuery()) : route('evaluations.index', $this->filterQuery());
        $periodLabel = $filters->label();
        $delta = fn ($v, $suffix = '') => $v === null ? null : ($v > 0 ? '▲ +' : ($v < 0 ? '▼ ' : '▶ ')).number_format($v, 1).$suffix;
        $deltaTone = fn ($v) => $v === null || $v == 0 ? 'bg-em-muted-bg text-em-soft' : ($v > 0 ? 'bg-em-ok-bg text-em-ok' : 'bg-em-bad-bg text-em-bad');
        $countDelta = $headline['count'] - $headline['previous_count'];
    @endphp

    <x-ui.page wide>
        <x-ui.header :title="$hospital->name.' Mystery Shopping Dashboard'"
            :subtitle="$hospital->name.' · '.$periodLabel.' · '.number_format($headline['count']).' '.Str::plural('visit', $headline['count']).' · '.$kpis['facilities'].' '.Str::plural('facility', $kpis['facilities']).($selectedForm ? ' · '.$selectedForm->title : ' · all surveys')">
            <x-slot name="eyebrowSlot">
                Mystery shopping
                @if($viewingAsAdmin)<x-ui.pill tone="info" class="font-medium">Viewing as admin</x-ui.pill>@endif
            </x-slot>
            <x-slot name="actions">
                <a href="{{ $listUrl }}" wire:navigate class="btn-secondary">All evaluations</a>
                <a href="{{ route('evaluations.export', ($viewingAsAdmin ? ['hospital' => $hospital->uuid] : []) + $this->filterQuery()) }}" class="btn-primary">Export CSV</a>
            </x-slot>
        </x-ui.header>

        @if($viewingAsAdmin)
            <p class="sr-only">Viewing as admin. Hospital users see this same dashboard for {{ $hospital->name }} only.</p>
        @endif

        <x-filters-bar :period="$period" :forms="$forms" :branches="$branches" :form="$form" :branch="$branch" :from="$from" :to="$to"
            :legend="['green' => (int) $ragGreen, 'amber' => (int) $ragAmber]" />

        @if(! $hasData)
            <x-empty-state title="No evaluations yet" message="They appear here as soon as a survey is submitted. Try a longer period if you expect data." />
        @else
            {{-- KPI tiles --}}
            <div class="grid gap-3.5 grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                @php $b = $band($headline['avg']); @endphp
                <div class="panel px-4 py-4 flex flex-col gap-1.5 min-w-0">
                    <div class="flex items-center justify-between gap-2"><span class="text-xs text-em-soft">Overall score</span>@if($b->key)<x-ui.band :pct="$headline['avg']" :green="$ragGreen" :amber="$ragAmber" :value="$b->label()" />@endif</div>
                    <div class="flex items-baseline gap-1"><span class="kpi-value">{{ $headline['avg'] === null ? '–' : number_format($headline['avg'], 1) }}</span>@if($headline['avg'] !== null)<span class="text-xs text-em-mute">%</span>@endif</div>
                    <div class="flex items-center gap-1.5 text-[11.5px] text-em-mute">
                        @if($headline['delta'] !== null)<span class="em-pill {{ $deltaTone($headline['delta']) }}">{{ $delta($headline['delta']) }}</span> pts vs previous period
                        @else No evaluations in the previous period @endif
                    </div>
                    <x-ui.sparkline :values="$overall['data']" class="mt-auto" />
                </div>
                <div class="panel px-4 py-4 flex flex-col gap-1.5 min-w-0">
                    <span class="text-xs text-em-soft">Visits completed</span>
                    <span class="kpi-value">{{ number_format($headline['count']) }}</span>
                    <div class="flex items-center gap-1.5 text-[11.5px] text-em-mute">
                        <span class="em-pill {{ $deltaTone($countDelta) }}">{{ $countDelta > 0 ? '▲ +' : ($countDelta < 0 ? '▼ ' : '▶ ') }}{{ $countDelta }}</span> {{ $headline['previous_count'] }} in previous period
                    </div>
                    <x-ui.sparkline :values="$overall['counts']" color="#6E666B" class="mt-auto" />
                </div>
                <div class="panel px-4 py-4 flex flex-col gap-1.5 min-w-0">
                    <span class="text-xs text-em-soft">Likelihood to recommend</span>
                    <div class="flex items-baseline gap-1"><span class="kpi-value">{{ $kpis['nps_avg'] === null ? '–' : number_format($kpis['nps_avg'], 1) }}</span>@if($kpis['nps_avg'] !== null)<span class="text-xs text-em-mute">/10</span>@endif</div>
                    <span class="text-[11.5px] text-em-mute">{{ $kpis['nps_count'] > 0 ? $kpis['nps_count'].' answers, scaled to 0–10' : 'Mark the “recommend” question in the mapping editor' }}</span>
                </div>
                @php $yb = $band($kpis['yes_pct']); @endphp
                <div class="panel px-4 py-4 flex flex-col gap-1.5 min-w-0">
                    <div class="flex items-center justify-between gap-2"><span class="text-xs text-em-soft">Critical items passed</span>@if($yb->key)<x-ui.band :pct="$kpis['yes_pct']" :green="$ragGreen" :amber="$ragAmber" :value="$yb->label()" />@endif</div>
                    <div class="flex items-baseline gap-1"><span class="kpi-value">{{ $kpis['yes_pct'] === null ? '–' : number_format($kpis['yes_pct'], 0) }}</span>@if($kpis['yes_pct'] !== null)<span class="text-xs text-em-mute">%</span>@endif</div>
                    <span class="text-[11.5px] text-em-mute">{{ $kpis['yes_total'] > 0 ? $kpis['yes_total'].' yes/no checks' : 'No yes/no checks answered' }}</span>
                </div>
                <div class="panel px-4 py-4 flex flex-col gap-1.5 min-w-0">
                    <span class="text-xs text-em-soft">Facilities evaluated</span>
                    <span class="kpi-value">{{ number_format($kpis['facilities']) }}</span>
                    <span class="text-[11.5px] text-em-mute">{{ $distribution['red'] > 0 ? $distribution['red'].' poor '.Str::plural('visit', $distribution['red']).($distribution['red'] === 1 ? ' needs' : ' need').' attention' : 'No poor visits' }}</span>
                </div>
            </div>

            {{-- Trend · outcome mix --}}
            <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_350px] gap-4">
                <x-ui.card :divider="false" title="Overall score trend" :subtitle="'Average per '.$trend['bucket'].', '.$periodLabel">
                    <x-slot name="actions">
                        <span class="inline-flex items-center gap-1.5 text-em-soft"><span class="h-0.5 w-4 bg-[#7470F0]" aria-hidden="true"></span>This period</span>
                        <span class="inline-flex items-center gap-1.5 text-em-soft"><span class="w-4 border-t border-dashed border-em-mute" aria-hidden="true"></span>Previous period</span>
                    </x-slot>
                    <div class="px-[22px] py-4">
                        @if(collect($overall['data'])->filter(fn ($v) => $v !== null)->isEmpty())
                            <p class="text-[13px] text-em-soft">No scored evaluations in this period.</p>
                        @else
                            <x-chart type="line" height="h-72" aria-label="Score trend" :guides="[['y' => $ragGreen, 'color' => '#C2B700'], ['y' => $ragAmber, 'color' => '#E3A048']]" :config="[
                                'data' => ['labels' => $trend['labels'], 'datasets' => [
                                    ['label' => 'This period', 'data' => $overall['data'], 'borderColor' => '#7470F0', 'backgroundColor' => 'rgba(116, 112, 240, 0.08)', 'fill' => 'start', 'spanGaps' => true, 'tension' => 0.3, 'borderWidth' => 2, 'pointRadius' => 3, 'pointHoverRadius' => 5, 'pointBackgroundColor' => '#ffffff', 'pointBorderColor' => '#7470F0', 'pointBorderWidth' => 1.5],
                                    ['label' => 'Previous period', 'data' => array_slice($previous['data'], 0, count($trend['labels'])), 'borderColor' => '#A9A2A6', 'borderDash' => [4, 4], 'borderWidth' => 1.5, 'fill' => false, 'spanGaps' => true, 'tension' => 0.3, 'pointRadius' => 0],
                                ]],
                                'options' => [
                                    'responsive' => true, 'maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false],
                                    'scales' => [
                                        'y' => ['suggestedMin' => 40, 'max' => 100, 'grid' => ['color' => '#F3F1F0'], 'border' => ['display' => false], 'ticks' => ['font' => ['size' => 11]]],
                                        'x' => ['grid' => ['display' => false], 'border' => ['display' => false], 'ticks' => ['font' => ['size' => 11], 'maxTicksLimit' => 8]],
                                    ],
                                    'plugins' => ['legend' => ['display' => false]],
                                ],
                            ]" />
                            <details class="mt-3 text-xs text-em-soft">
                                <summary class="cursor-pointer">Show values</summary>
                                <table class="table-base mt-2"><thead><tr><th>Period</th><th>Average</th><th>Visits</th></tr></thead>
                                <tbody>@foreach($trend['labels'] as $i => $label)<tr><td>{{ $label }}</td><td class="tabular-nums">{{ $overall['data'][$i] === null ? '–' : number_format($overall['data'][$i], 1).'%' }}</td><td class="tabular-nums">{{ $overall['counts'][$i] }}</td></tr>@endforeach</tbody></table>
                            </details>
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card :divider="false" title="Outcome mix" :subtitle="$scored.' '.Str::plural('visit', $scored).' by score band'">
                    <div class="px-[22px] py-4">
                        @if($scored === 0)
                            <p class="text-[13px] text-em-soft">No scored evaluations yet.</p>
                        @else
                            <x-chart type="doughnut" height="h-44" aria-label="Outcome mix" :center-text="['value' => number_format(100 * $distribution['green'] / $scored, 0).'%', 'label' => 'rated Good']" :config="[
                                'data' => ['labels' => ['Good', 'Watch', 'Poor'], 'datasets' => [['data' => [$distribution['green'], $distribution['amber'], $distribution['red']], 'backgroundColor' => ['#C2B700', '#E3A048', '#F44336'], 'borderColor' => '#ffffff', 'borderWidth' => 2, 'hoverOffset' => 4]]],
                                'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'cutout' => '76%', 'plugins' => ['legend' => ['display' => false]]],
                            ]" />
                            <ul class="mt-4 flex flex-col gap-2 text-[13px]">
                                @foreach(['green' => ['Good', 'bg-rag-green'], 'amber' => ['Watch', 'bg-rag-amber'], 'red' => ['Poor', 'bg-rag-red']] as $key => [$label, $dot])
                                    <li class="grid grid-cols-[1fr_auto_3rem] items-center gap-3">
                                        <span class="flex items-center gap-2"><span class="h-2 w-2 rounded-full {{ $dot }}" aria-hidden="true"></span>{{ $label }}</span>
                                        <span class="tabular-nums text-em-soft">{{ $distribution[$key] }}</span>
                                        <span class="tabular-nums text-right font-medium">{{ number_format(100 * $distribution[$key] / $scored, 0) }}%</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if($distribution['unscored'] > 0)<p class="text-xs text-em-mute mt-2">{{ $distribution['unscored'] }} without a score.</p>@endif
                        @endif
                    </div>
                </x-ui.card>
            </div>

            {{-- Score by touchpoint --}}
            <x-ui.card :divider="false" :title="$selectedForm ? 'Score by touchpoint' : 'Score by survey'">
                <div class="px-[22px] pt-1 pb-5">
                    <p class="text-xs text-em-soft mb-3">{{ $selectedForm ? 'In patient-journey order' : 'Pick a survey above to see touchpoint scores — touchpoints differ between surveys.' }}</p>
                    @php
                        $bars = $selectedForm
                            ? collect($sections ?? [])->map(fn ($s) => ['name' => $s['name'], 'avg' => $s['avg'], 'count' => $s['count']])
                            : collect($trend['series'])->map(fn ($s) => ['name' => $s['label'], 'avg' => array_sum($s['counts']) > 0 ? collect($s['data'])->zip($s['counts'])->sum(fn ($p) => ($p[0] ?? 0) * $p[1]) / array_sum($s['counts']) : null, 'count' => array_sum($s['counts'])])->filter(fn ($r) => $r['avg'] !== null);
                    @endphp
                    @if($bars->isEmpty())
                        <p class="text-[13px] text-em-soft">No scores in this period.</p>
                    @else
                        <ol class="flex flex-col gap-3">
                            @foreach($bars as $row)
                                @php $rb = $band($row['avg']); @endphp
                                <li>
                                    <div class="flex justify-between text-[13px] mb-1.5"><span class="truncate pr-2">{{ $row['name'] }}</span><span class="tabular-nums font-semibold {{ $rb->text() }}" title="{{ $row['count'] }} {{ Str::plural('answer', $row['count']) }}">{{ number_format($row['avg'], 0) }}</span></div>
                                    <div class="h-1.5 rounded-full bg-[#F3F1F0] overflow-hidden" role="img" aria-label="{{ $row['name'] }} {{ number_format($row['avg'], 1) }} percent"><div class="h-full rounded-full {{ $rb->bar() }}" style="width: {{ $row['avg'] }}%"></div></div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>
            </x-ui.card>

            {{-- Facility ranking · heat-map --}}
            <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,5fr)_minmax(0,9fr)] gap-4">
                <x-ui.card :divider="false" title="Facility ranking">
                    <div class="px-[22px] pt-1 pb-4">
                        <p class="text-xs text-em-soft mb-2">{{ $ranking['bottom'] === [] ? 'All '.$ranking['total'].' '.Str::plural('facility', $ranking['total']).', best first' : 'Top and bottom 5 of '.$ranking['total'].' facilities' }}</p>
                        @if($ranking['top'] === [])
                            <p class="text-[13px] text-em-soft">No scored evaluations in this period.</p>
                        @else
                            <ol class="divide-y divide-[#F3F1F0]">
                                @foreach([$ranking['top'], $ranking['bottom']] as $part => $rows)
                                    @if($part === 1 && $rows !== [])<li class="eyebrow pt-3 pb-1">Bottom</li>@endif
                                    @foreach($rows as $i => $f)
                                        @php $fb = $band($f['avg']); @endphp
                                        <li class="grid grid-cols-[1.25rem_minmax(0,1fr)_6.5rem_2rem] items-center gap-3 py-2.5">
                                            <span class="text-xs text-em-mute tabular-nums">{{ $part === 0 ? $i + 1 : $ranking['total'] - count($rows) + $i + 1 }}</span>
                                            <span class="min-w-0"><span class="block font-medium truncate" title="{{ $f['name'] }}">{{ $f['name'] }}</span><span class="block text-[11.5px] text-em-mute">{{ $f['count'] }} {{ Str::plural('visit', $f['count']) }}</span></span>
                                            <span class="h-1.5 rounded-full bg-[#F3F1F0] overflow-hidden"><span class="block h-full rounded-full {{ $fb->bar() }}" style="width: {{ $f['avg'] }}%"></span></span>
                                            <span class="tabular-nums text-right font-semibold {{ $fb->text() }}">{{ number_format($f['avg'], 0) }}</span>
                                        </li>
                                    @endforeach
                                @endforeach
                            </ol>
                        @endif
                    </div>
                </x-ui.card>

                <x-ui.card :divider="false" title="Touchpoint heat-map">
                    <div class="px-[22px] pt-1 pb-5">
                        <p class="text-xs text-em-soft mb-3">Facility × {{ $selectedForm ? 'touchpoint' : 'survey' }} · poor scores are filled in</p>
                        @if($heatmap['rows'] === [] || $heatmap['columns'] === [])
                            <p class="text-[13px] text-em-soft">No scored evaluations in this period.</p>
                        @else
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-xs border-separate border-spacing-1">
                                    <thead>
                                        <tr>
                                            <th class="text-left eyebrow font-normal pb-1 pr-2 align-bottom">Facility</th>
                                            @foreach($heatmap['columns'] as $col)
                                                <th class="pb-1 font-normal text-em-soft text-center align-bottom max-w-[6.5rem] text-[11.5px] leading-tight"><span class="line-clamp-2" title="{{ $col }}">{{ $col }}</span></th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($heatmap['rows'] as $row)
                                            <tr>
                                                <th scope="row" class="text-left pr-2 py-0.5 max-w-[12rem]"><span class="block font-medium text-[13px] text-em-ink truncate" title="{{ $row['label'] }}">{{ $row['label'] }}</span><span class="block font-normal text-[11.5px] text-em-mute">{{ $row['count'] }} {{ Str::plural('visit', $row['count']) }}</span></th>
                                                @foreach($heatmap['columns'] as $col)
                                                    @php $v = $row['cells'][$col]; $cb = $band($v); @endphp
                                                    <td><div class="h-9 min-w-[3.25rem] rounded-lg flex items-center justify-center text-[13px] font-medium tabular-nums {{ $cb->cell() }}" title="{{ $row['label'] }} · {{ $col }}: {{ $v === null ? 'no data' : number_format($v, 1).'% ('.$cb->label().')' }}">{{ $v === null ? '—' : number_format($v, 0) }}</div></td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </x-ui.card>
            </div>

            {{-- Question-level · live feed --}}
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                @foreach([['Areas of concern', 'Five lowest-scoring questions', $weakest, 'bg-em-bad-bg text-em-bad', 'Pick a survey above to see the questions that need attention.'], ['Areas of strength', 'Five highest-scoring questions', $strongest, 'bg-em-ok-bg text-em-ok', 'Pick a survey above to see what is going well.']] as [$title, $sub, $items, $badge, $empty])
                    <x-ui.card :divider="false" :title="$title">
                        <div class="px-[22px] pt-1 pb-4">
                            <p class="text-xs text-em-soft mb-1">{{ $sub }}</p>
                            @if($selectedForm === null)
                                <p class="text-[13px] text-em-soft pt-2">{{ $empty }}</p>
                            @elseif(empty($items))
                                <p class="text-[13px] text-em-soft pt-2">No scored answers in this period.</p>
                            @else
                                <ol class="divide-y divide-[#F3F1F0]">
                                    @foreach($items as $q)
                                        <li class="py-3 flex items-start gap-3">
                                            <span class="mt-0.5 h-5 w-5 shrink-0 rounded-full {{ $badge }} text-[11px] font-semibold flex items-center justify-center" aria-hidden="true">{{ $loop->iteration }}</span>
                                            <span class="min-w-0 flex-1"><span class="block" title="{{ $q['title'] }}">{{ $q['title'] }}</span>@if($q['section'])<span class="block text-[11.5px] text-em-mute">{{ $q['section'] }} · {{ $q['count'] }} {{ Str::plural('answer', $q['count']) }}</span>@endif</span>
                                            <span class="tabular-nums whitespace-nowrap font-semibold {{ $band($q['avg_pct'])->text() }}">{{ number_format($q['avg_pct'], 0) }}%</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </div>
                    </x-ui.card>
                @endforeach

                <x-ui.card :divider="false" title="Live evaluation feed">
                    <x-slot name="actions"><a href="{{ $listUrl }}" wire:navigate class="text-em-accent hover:underline">View all</a></x-slot>
                    <div class="px-[22px] pt-1 pb-3">
                        <p class="text-xs text-em-soft mb-1">Newest first, as soon as each report is published</p>
                        @if($recent->isEmpty())
                            <p class="text-[13px] text-em-soft pt-2">No evaluations in this period.</p>
                        @else
                            <ul class="divide-y divide-[#F3F1F0]">
                                @foreach($recent as $e)
                                    <li wire:key="recent-{{ $e->id }}">
                                        <a href="{{ route('evaluations.show', $e) }}" wire:navigate class="flex items-center justify-between gap-3 py-2.5 hover:text-em-accent">
                                            <span class="min-w-0">
                                                <span class="block font-medium truncate">{{ $e->infoValue('facility') ?? $e->form->title }}</span>
                                                <span class="block text-[11.5px] text-em-mute truncate">{{ $e->submitted_at?->format('j M Y, H:i') }} · {{ $e->form->title }}</span>
                                            </span>
                                            <x-ui.band :pct="$e->overall_pct" :rag="$e->rag" />
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </x-ui.card>
            </div>
        @endif
    </x-ui.page>
</div>
