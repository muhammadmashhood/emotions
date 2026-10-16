<div>
    @php
        $ragOf = fn ($pct) => \App\Enums\Rag::fromPct($pct === null ? null : (float) $pct, $ragGreen, $ragAmber);
        $ragText = fn ($pct) => match ($ragOf($pct)) { \App\Enums\Rag::Green => 'text-rag-green', \App\Enums\Rag::Amber => 'text-rag-amber', \App\Enums\Rag::Red => 'text-rag-red', default => 'text-ink-soft' };
        $ragBg = fn ($pct) => match ($ragOf($pct)) { \App\Enums\Rag::Green => 'bg-rag-green', \App\Enums\Rag::Amber => 'bg-rag-amber', \App\Enums\Rag::Red => 'bg-rag-red', default => 'bg-rag-neutral/40' };
        $cell = fn ($pct) => match ($ragOf($pct)) { \App\Enums\Rag::Green => 'bg-rag-green text-white', \App\Enums\Rag::Amber => 'bg-rag-amber text-white', \App\Enums\Rag::Red => 'bg-rag-red text-white', default => 'bg-paper text-ink-soft' };
        $hasData = $headline['count'] > 0 || $headline['previous_count'] > 0;
    @endphp

    <x-page-header title="Patient Experience — Mystery Shopping Dashboard" :subtitle="$hospital->name.' · '.ucfirst($filters->label()).' · '.$headline['count'].' '.Str::plural('evaluation', $headline['count']).' · '.$kpis['facilities'].' '.Str::plural('facility', $kpis['facilities']).($selectedForm ? ' · '.$selectedForm->title : ' · all surveys')">
        <x-slot name="actions">
            <a href="{{ $viewingAsAdmin ? route('hospitals.evaluations.index', [$hospital] + $this->filterQuery()) : route('evaluations.index', $this->filterQuery()) }}" wire:navigate class="btn-secondary">All evaluations</a>
            <a href="{{ route('evaluations.export', ($viewingAsAdmin ? ['hospital' => $hospital->uuid] : []) + $this->filterQuery()) }}" class="btn-primary">Export CSV</a>
        </x-slot>
    </x-page-header>

    <x-page>
        @if($viewingAsAdmin)
            <x-alert type="info">Viewing as admin. Hospital users see this same dashboard for {{ $hospital->name }} only.</x-alert>
        @endif

        <x-filters-bar :period="$period" :forms="$forms" :branches="$branches" :form="$form" :branch="$branch" :from="$from" :to="$to" />

        @if(! $hasData)
            <x-empty-state title="No evaluations yet" message="They appear here as soon as a survey is submitted. Try a longer period if you expect data." />
        @else
            {{-- KPI row --}}
            <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
                <x-kpi label="Overall score" :value="$headline['avg'] === null ? '–' : number_format($headline['avg'], 1)" :unit="$headline['avg'] === null ? null : '%'"
                       :tone="$ragOf($headline['avg'])?->value" :delta="$headline['delta']" :delta-label="'vs previous '.$filters->lengthInDays().' days'"
                       :note="$headline['previous_count'] === 0 ? 'No evaluations in the previous period' : null" />
                <x-kpi label="Evaluations" :value="number_format($headline['count'])" :note="$headline['previous_count'].' in the previous period'" />
                <x-kpi label="Likelihood to recommend" :value="$kpis['nps_avg'] === null ? '–' : number_format($kpis['nps_avg'], 1)" :unit="$kpis['nps_avg'] === null ? null : '/10'"
                       :note="$kpis['nps_count'] > 0 ? $kpis['nps_count'].' answers' : 'No recommendation question answered'" />
                <x-kpi label="Critical items passed" :value="$kpis['yes_pct'] === null ? '–' : number_format($kpis['yes_pct'], 0)" :unit="$kpis['yes_pct'] === null ? null : '%'"
                       :tone="$ragOf($kpis['yes_pct'])?->value" :note="$kpis['yes_total'] > 0 ? $kpis['yes_total'].' yes/no checks' : 'No yes/no checks answered'" />
                <x-kpi label="Facilities evaluated" :value="number_format($kpis['facilities'])" :note="$distribution['red'] > 0 ? $distribution['red'].' red '.Str::plural('evaluation', $distribution['red']).' need attention' : 'No red evaluations'" />
            </div>

            {{-- Trend · outcome mix · section scores --}}
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                <x-card title="Overall score — trend" :subtitle="'Average per '.$trend['bucket'].', '.$filters->label()" class="xl:col-span-6">
                    @php $hasTrend = collect($trend['series'])->flatMap(fn ($s) => $s['data'])->filter(fn ($v) => $v !== null)->isNotEmpty(); @endphp
                    @if(! $hasTrend)
                        <p class="text-sm text-ink-soft">No scored evaluations in this period.</p>
                    @else
                        @php
                            $palette = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6'];
                            $single = count($trend['series']) === 1;
                            $datasets = collect($trend['series'])->values()->map(fn ($s, $i) => [
                                'label' => $s['label'], 'data' => $s['data'],
                                'borderColor' => $single ? '#2457F5' : $palette[$i % count($palette)],
                                'backgroundColor' => $single ? 'rgba(36, 87, 245, 0.10)' : $palette[$i % count($palette)],
                                'fill' => $single, 'spanGaps' => true, 'tension' => 0.35, 'borderWidth' => 2, 'pointRadius' => 4, 'pointHoverRadius' => 6,
                                'pointBackgroundColor' => '#ffffff', 'pointBorderWidth' => 2,
                            ])->all();
                        @endphp
                        <x-chart type="line" height="h-72" aria-label="Score trend" :config="[
                            'data' => ['labels' => $trend['labels'], 'datasets' => $datasets],
                            'options' => [
                                'responsive' => true, 'maintainAspectRatio' => false, 'interaction' => ['mode' => 'index', 'intersect' => false],
                                'scales' => [
                                    'y' => ['min' => 0, 'max' => 100, 'grid' => ['color' => 'rgba(220,227,231,0.6)'], 'border' => ['display' => false], 'ticks' => ['color' => '#4E6875', 'font' => ['size' => 11]]],
                                    'x' => ['grid' => ['display' => false], 'border' => ['display' => false], 'ticks' => ['color' => '#4E6875', 'font' => ['size' => 11], 'maxTicksLimit' => 12]],
                                ],
                                'plugins' => ['legend' => ['display' => ! $single, 'position' => 'bottom', 'labels' => ['usePointStyle' => true, 'boxWidth' => 8, 'color' => '#123A4A']]],
                            ],
                        ]" />
                        <details class="mt-3 text-xs text-ink-soft">
                            <summary class="cursor-pointer">Show values</summary>
                            <table class="table-base mt-2"><thead><tr><th>Period</th>@foreach($trend['series'] as $s)<th>{{ $s['label'] }}</th>@endforeach</tr></thead>
                            <tbody>@foreach($trend['labels'] as $i => $label)<tr><td>{{ $label }}</td>@foreach($trend['series'] as $s)<td class="tabular-nums">{{ $s['data'][$i] === null ? '–' : number_format($s['data'][$i], 1).'% (n='.$s['counts'][$i].')' }}</td>@endforeach</tr>@endforeach</tbody></table>
                        </details>
                    @endif
                </x-card>

                <x-card title="Outcome mix" :subtitle="'Green ≥ '.number_format($ragGreen, 0).'% · amber ≥ '.number_format($ragAmber, 0).'%'" class="xl:col-span-3">
                    @php $scored = $distribution['green'] + $distribution['amber'] + $distribution['red']; @endphp
                    @if($scored === 0)
                        <p class="text-sm text-ink-soft">No scored evaluations yet.</p>
                    @else
                        <x-chart type="doughnut" height="h-44" aria-label="Outcome mix" :center-text="['value' => $headline['avg'] === null ? '–' : number_format($headline['avg'], 0).'%', 'label' => 'avg']" :config="[
                            'data' => ['labels' => ['Green', 'Amber', 'Red'], 'datasets' => [['data' => [$distribution['green'], $distribution['amber'], $distribution['red']], 'backgroundColor' => ['#2F855A', '#B7791F', '#B83A33'], 'borderColor' => '#ffffff', 'borderWidth' => 2, 'hoverOffset' => 4]]],
                            'options' => ['responsive' => true, 'maintainAspectRatio' => false, 'cutout' => '72%', 'plugins' => ['legend' => ['display' => false]]],
                        ]" />
                        <ul class="mt-3 space-y-1.5 text-sm">
                            @foreach(['green' => ['Green', 'bg-rag-green'], 'amber' => ['Amber', 'bg-rag-amber'], 'red' => ['Red', 'bg-rag-red']] as $key => [$label, $cls])
                                <li class="flex items-center justify-between gap-2">
                                    <span class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full {{ $cls }}" aria-hidden="true"></span>{{ $label }}</span>
                                    <span class="tabular-nums text-ink-soft">{{ $distribution[$key] }} · {{ number_format(100 * $distribution[$key] / $scored, 0) }}%</span>
                                </li>
                            @endforeach
                        </ul>
                        @if($distribution['unscored'] > 0)<p class="text-xs text-ink-soft mt-2">{{ $distribution['unscored'] }} without a score.</p>@endif
                    @endif
                </x-card>

                <x-card title="Score by section" :subtitle="$selectedForm ? 'Average per section, form order' : 'Average per survey'" class="xl:col-span-3">
                    @if($selectedForm === null)
                        @php $bySurvey = collect($trend['series'])->map(fn ($s) => ['name' => $s['label'], 'avg' => collect($s['data'])->filter(fn ($v) => $v !== null)->avg(), 'count' => array_sum($s['counts'])])->filter(fn ($r) => $r['avg'] !== null); @endphp
                        @if($bySurvey->isEmpty())
                            <p class="text-sm text-ink-soft">Pick a survey above to see section scores — sections differ between surveys.</p>
                        @else
                            <p class="text-xs text-ink-soft mb-3">Pick a survey above to see section scores — sections differ between surveys.</p>
                            <ol class="space-y-3">
                                @foreach($bySurvey as $s)
                                    <li>
                                        <div class="flex justify-between text-sm mb-1"><span class="font-medium truncate pr-2">{{ $s['name'] }}</span><span class="tabular-nums {{ $ragText($s['avg']) }}">{{ number_format($s['avg'], 0) }}</span></div>
                                        <div class="h-2.5 rounded-full bg-paper overflow-hidden"><div class="h-full rounded-full {{ $ragBg($s['avg']) }}" style="width: {{ $s['avg'] }}%"></div></div>
                                    </li>
                                @endforeach
                            </ol>
                        @endif
                    @elseif(empty($sections))
                        <p class="text-sm text-ink-soft">No section scores in this period.</p>
                    @else
                        <ol class="space-y-3">
                            @foreach($sections as $s)
                                <li>
                                    <div class="flex justify-between text-sm mb-1"><span class="font-medium truncate pr-2">{{ $s['name'] }}</span><span class="tabular-nums {{ $ragText($s['avg']) }}">{{ number_format($s['avg'], 0) }} <span class="text-ink-soft text-xs">({{ $s['count'] }})</span></span></div>
                                    <div class="h-2.5 rounded-full bg-paper overflow-hidden" role="img" aria-label="{{ $s['name'] }} {{ number_format($s['avg'], 1) }} percent"><div class="h-full rounded-full {{ $ragBg($s['avg']) }}" style="width: {{ $s['avg'] }}%"></div></div>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>
            </div>

            {{-- Facility ranking · heat-map --}}
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                <x-card title="Facility ranking" :subtitle="$ranking['bottom'] === [] ? 'All '.$ranking['total'].' facilities, best first' : 'Top and bottom 5 of '.$ranking['total'].' facilities'" class="xl:col-span-5">
                    @if($ranking['top'] === [])
                        <p class="text-sm text-ink-soft">No scored evaluations in this period.</p>
                    @else
                        <ol class="space-y-2.5">
                            @foreach($ranking['top'] as $f)
                                <li class="grid grid-cols-[1fr_auto] gap-x-3 items-center text-sm">
                                    <span class="truncate" title="{{ $f['name'] }}">{{ $f['name'] }} <span class="text-xs text-ink-soft">· {{ $f['count'] }}</span></span>
                                    <span class="flex items-center gap-2 w-40">
                                        <span class="flex-1 h-2 rounded-full bg-paper overflow-hidden"><span class="block h-full rounded-full {{ $ragBg($f['avg']) }}" style="width: {{ $f['avg'] }}%"></span></span>
                                        <span class="tabular-nums w-8 text-right {{ $ragText($f['avg']) }}">{{ number_format($f['avg'], 0) }}</span>
                                    </span>
                                </li>
                            @endforeach
                            @if($ranking['bottom'] !== [])
                                <li class="eyebrow pt-2 border-t border-line">Bottom</li>
                                @foreach($ranking['bottom'] as $f)
                                    <li class="grid grid-cols-[1fr_auto] gap-x-3 items-center text-sm">
                                        <span class="truncate" title="{{ $f['name'] }}">{{ $f['name'] }} <span class="text-xs text-ink-soft">· {{ $f['count'] }}</span></span>
                                        <span class="flex items-center gap-2 w-40">
                                            <span class="flex-1 h-2 rounded-full bg-paper overflow-hidden"><span class="block h-full rounded-full {{ $ragBg($f['avg']) }}" style="width: {{ $f['avg'] }}%"></span></span>
                                            <span class="tabular-nums w-8 text-right {{ $ragText($f['avg']) }}">{{ number_format($f['avg'], 0) }}</span>
                                        </span>
                                    </li>
                                @endforeach
                            @endif
                        </ol>
                    @endif
                </x-card>

                <x-card title="Touchpoint heat-map" :subtitle="($selectedForm ? 'Sections' : 'Surveys').' by facility · green ≥ '.number_format($ragGreen, 0).' · amber ≥ '.number_format($ragAmber, 0).' · red below'" class="xl:col-span-7" :padding="false">
                    @if($heatmap['rows'] === [] || $heatmap['columns'] === [])
                        <p class="px-5 pb-5 text-sm text-ink-soft">No scored evaluations in this period.</p>
                    @else
                        <div class="overflow-x-auto px-5 pb-5">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr>
                                        <th class="text-left eyebrow pb-2 pr-3 font-semibold">Facility</th>
                                        @foreach($heatmap['columns'] as $col)
                                            <th class="eyebrow pb-2 px-1 font-semibold text-center max-w-[7rem]"><span class="block truncate" title="{{ $col }}">{{ $col }}</span></th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($heatmap['rows'] as $row)
                                        <tr>
                                            <th scope="row" class="text-left font-medium text-sm pr-3 py-1 whitespace-nowrap max-w-[12rem] truncate" title="{{ $row['label'] }}">{{ $row['label'] }} <span class="text-ink-soft font-normal">· {{ $row['count'] }}</span></th>
                                            @foreach($heatmap['columns'] as $col)
                                                @php $v = $row['cells'][$col]; @endphp
                                                <td class="p-1"><div class="h-9 min-w-[3.25rem] rounded-lg flex items-center justify-center font-semibold tabular-nums {{ $cell($v) }}" title="{{ $row['label'] }} · {{ $col }}: {{ $v === null ? 'no data' : number_format($v, 1).'%' }}">{{ $v === null ? '—' : number_format($v, 0) }}</div></td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            </div>

            {{-- Question-level · live feed --}}
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-4">
                <x-card title="Weakest questions" subtitle="Lowest average score, scored questions only" class="xl:col-span-4">
                    @if($selectedForm === null)
                        <p class="text-sm text-ink-soft">Pick a survey above to see the lowest-scoring questions.</p>
                    @elseif(empty($weakest))
                        <p class="text-sm text-ink-soft">No scored answers in this period.</p>
                    @else
                        <ol class="divide-y divide-line/70 text-sm">
                            @foreach($weakest as $q)
                                <li class="py-2 flex justify-between gap-3">
                                    <span class="min-w-0"><span class="font-medium block truncate" title="{{ $q['title'] }}">{{ $q['title'] }}</span>@if($q['section'])<span class="block text-xs text-ink-soft">{{ $q['section'] }}</span>@endif</span>
                                    <span class="tabular-nums whitespace-nowrap {{ $ragText($q['avg_pct']) }}">{{ number_format($q['avg_pct'], 0) }}% <span class="text-ink-soft text-xs">({{ $q['count'] }})</span></span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </x-card>

                <x-card title="Yes/No compliance" subtitle="% Yes among Yes/No answers" class="xl:col-span-4">
                    @if($selectedForm === null)
                        <p class="text-sm text-ink-soft">Pick a survey above to see compliance per question.</p>
                    @elseif(empty($compliance))
                        <p class="text-sm text-ink-soft">No yes/no answers in this period.</p>
                    @else
                        <ul class="space-y-2.5 text-sm">
                            @foreach($compliance as $c)
                                <li>
                                    <div class="flex justify-between gap-3 mb-1"><span class="font-medium truncate" title="{{ $c['title'] }}">{{ $c['title'] }}</span><span class="tabular-nums whitespace-nowrap {{ $ragText($c['pct_yes']) }}">{{ number_format($c['pct_yes'], 0) }}% <span class="text-ink-soft text-xs">({{ $c['yes'] }}/{{ $c['yes'] + $c['no'] }})</span></span></div>
                                    <div class="h-1.5 rounded-full bg-paper overflow-hidden"><div class="h-full rounded-full {{ $ragBg($c['pct_yes']) }}" style="width: {{ $c['pct_yes'] }}%"></div></div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>

                <x-card title="Live evaluation feed" subtitle="Latest submissions, newest first" class="xl:col-span-4" :padding="false">
                    @if($recent->isEmpty())
                        <p class="px-5 pb-5 text-sm text-ink-soft">No evaluations in this period.</p>
                    @else
                        <ul class="divide-y divide-line/70">
                            @foreach($recent as $e)
                                <li wire:key="recent-{{ $e->id }}">
                                    <a href="{{ route('evaluations.show', $e) }}" wire:navigate class="flex items-center justify-between gap-3 px-5 py-2.5 hover:bg-paper/70 transition">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium truncate">{{ $e->infoValue('facility') ?? $e->form->title }}</span>
                                            <span class="block text-xs text-ink-soft truncate">{{ $e->submitted_at?->format('d M Y H:i') }} · {{ $e->form->title }}@if($e->infoValue('location')) · {{ $e->infoValue('location') }}@endif</span>
                                        </span>
                                        <x-rag-pill :pct="$e->overall_pct" :rag="$e->rag" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            </div>
        @endif
    </x-page>
</div>
