<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <x-card title="Average score (90 days)">
        <p class="text-4xl font-semibold tabular-nums">{{ $headline['avg'] === null ? '–' : number_format($headline['avg'], 1).'%' }}</p>
        <p class="text-sm text-ink-soft">{{ $headline['count'] }} evaluations @if($headline['delta'] !== null), {{ $headline['delta'] >= 0 ? '+' : '' }}{{ number_format($headline['delta'], 1) }} pts vs previous 90 days @endif</p>
    </x-card>
    <x-card title="Distribution">
        <p class="text-sm"><span class="text-rag-green font-semibold">{{ $distribution['green'] }}</span> green · <span class="text-rag-amber font-semibold">{{ $distribution['amber'] }}</span> amber · <span class="text-rag-red font-semibold">{{ $distribution['red'] }}</span> red</p>
    </x-card>
    <x-card title="Latest" :padding="false">
        @if($recent->isEmpty())
            <p class="p-4 text-sm text-ink-soft">No evaluations yet.</p>
        @else
            <ul class="divide-y divide-line text-sm">
                @foreach($recent as $e)
                    <li class="px-4 py-2 flex justify-between gap-2"><a href="{{ route('evaluations.show', $e) }}" wire:navigate class="underline">{{ $e->submitted_at?->format('d M') }} · {{ Str::limit($e->infoValue('facility') ?? $e->form->title, 24) }}</a><x-rag-pill :pct="$e->overall_pct" :rag="$e->rag" /></li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
