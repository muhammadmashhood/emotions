@props(['scores'])
{{-- One tile per section in form order; fill proportional to pct; "–" for unanswered. --}}
<div {{ $attributes->merge(['class' => 'flex gap-1 sm:gap-2 overflow-x-auto pb-2']) }} role="list" aria-label="Section scores">
    @foreach($scores as $score)
        @php
            $pct = $score->pct;
            $rag = $pct === null ? null : \App\Enums\Rag::fromPct((float) $pct, 80, 60);
            $fill = match ($rag) { \App\Enums\Rag::Green => 'bg-rag-green', \App\Enums\Rag::Amber => 'bg-rag-amber', \App\Enums\Rag::Red => 'bg-rag-red', default => 'bg-rag-neutral/40' };
        @endphp
        <a href="#section-{{ $score->section_id }}" role="listitem"
           class="group flex-1 min-w-[4.5rem] flex flex-col items-center gap-1 text-center rounded-md p-1 hover:bg-paper focus-visible:bg-paper"
           x-on:click.prevent="document.getElementById('section-{{ $score->section_id }}')?.dispatchEvent(new CustomEvent('open-section')); document.getElementById('section-{{ $score->section_id }}')?.scrollIntoView({behavior: 'smooth', block: 'start'})"
           title="{{ $score->section_name }}: {{ $pct === null ? 'not answered' : number_format((float) $pct, 1).'%' }}">
            <span class="text-xs font-semibold tabular-nums {{ $pct === null ? 'text-ink-soft' : 'text-ink' }}">{{ $pct === null ? '–' : number_format((float) $pct, 0).'%' }}</span>
            <span class="relative w-full h-20 rounded bg-paper border border-line overflow-hidden" aria-hidden="true">
                <span class="absolute bottom-0 inset-x-0 {{ $fill }} transition-all" style="height: {{ $pct === null ? 0 : max(4, (float) $pct) }}%"></span>
            </span>
            <span class="text-[11px] leading-tight text-ink-soft line-clamp-2">{{ $score->section_name }}</span>
        </a>
    @endforeach
</div>
