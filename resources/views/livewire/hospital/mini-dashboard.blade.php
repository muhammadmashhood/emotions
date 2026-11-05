<div class="grid gap-3.5 grid-cols-1 md:grid-cols-3">
    @php $scored = $distribution['green'] + $distribution['amber'] + $distribution['red']; @endphp
    <div class="panel px-[18px] py-4 flex flex-col gap-1.5">
        <div class="flex items-center justify-between gap-2">
            <span class="text-xs text-em-soft">Average score · 90 days</span>
            @if($headline['avg'] !== null)<x-ui.band :pct="$headline['avg']" :value="\App\Support\ScoreBand::of($headline['avg'])->label()" />@endif
        </div>
        <span class="kpi-value">{{ $headline['avg'] === null ? '–' : number_format($headline['avg'], 1).'%' }}</span>
        <span class="text-[11.5px] text-em-mute">{{ $headline['count'] }} {{ Str::plural('visit', $headline['count']) }}@if($headline['delta'] !== null) · {{ $headline['delta'] >= 0 ? '+' : '' }}{{ number_format($headline['delta'], 1) }} pts vs previous 90 days @endif</span>
    </div>
    <div class="panel px-[18px] py-4 flex flex-col gap-2.5">
        <span class="text-xs text-em-soft">Outcome mix</span>
        @if($scored === 0)
            <span class="text-[13px] text-em-soft">No scored visits yet.</span>
        @else
            <div class="flex h-2 rounded-full overflow-hidden bg-[#F3F1F0]" role="img" aria-label="{{ $distribution['green'] }} good, {{ $distribution['amber'] }} watch, {{ $distribution['red'] }} poor">
                <span class="bg-rag-green" style="width: {{ 100 * $distribution['green'] / $scored }}%"></span>
                <span class="bg-rag-amber" style="width: {{ 100 * $distribution['amber'] / $scored }}%"></span>
                <span class="bg-rag-red" style="width: {{ 100 * $distribution['red'] / $scored }}%"></span>
            </div>
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-[12.5px]">
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rag-green" aria-hidden="true"></span>{{ $distribution['green'] }} good</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rag-amber" aria-hidden="true"></span>{{ $distribution['amber'] }} watch</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-rag-red" aria-hidden="true"></span>{{ $distribution['red'] }} poor</span>
            </div>
        @endif
    </div>
    <div class="panel px-[18px] py-4 flex flex-col gap-1">
        <span class="text-xs text-em-soft">Latest visits</span>
        @if($recent->isEmpty())
            <span class="text-[13px] text-em-soft">No evaluations yet.</span>
        @else
            <ul class="divide-y divide-[#F3F1F0]">
                @foreach($recent as $e)
                    <li class="py-1.5 flex items-center justify-between gap-2">
                        <a href="{{ route('evaluations.show', $e) }}" wire:navigate class="truncate hover:text-em-accent">{{ $e->submitted_at?->format('j M') }} · {{ Str::limit($e->infoValue('facility') ?? $e->form->title, 28) }}</a>
                        <x-ui.band :pct="$e->overall_pct" :rag="$e->rag" />
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
