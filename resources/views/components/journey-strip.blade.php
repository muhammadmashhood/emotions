@props(['scores', 'green' => 80, 'amber' => 60])
{{-- One tile per section in form order; fill proportional to pct; "–" for unanswered. Click scrolls to and opens the section. --}}
<div {{ $attributes->merge(['class' => 'grid gap-2']) }} style="grid-template-columns: repeat({{ max(1, count($scores)) }}, minmax(4.5rem, 1fr));" role="list" aria-label="Section scores">
    @foreach($scores as $score)
        @php
            $pct = $score->pct;
            $rag = $pct === null ? null : \App\Enums\Rag::fromPct((float) $pct, $green, $amber);
            $fill = match ($rag) { \App\Enums\Rag::Green => 'from-rag-green to-rag-green/70', \App\Enums\Rag::Amber => 'from-rag-amber to-rag-amber/70', \App\Enums\Rag::Red => 'from-rag-red to-rag-red/70', default => 'from-rag-neutral/40 to-rag-neutral/20' };
            $text = match ($rag) { \App\Enums\Rag::Green => 'text-rag-green', \App\Enums\Rag::Amber => 'text-rag-amber', \App\Enums\Rag::Red => 'text-rag-red', default => 'text-ink-soft' };
        @endphp
        <a href="#section-{{ $score->section_id }}" role="listitem"
           class="group flex flex-col items-center gap-1.5 text-center rounded-xl p-1.5 hover:bg-paper focus-visible:bg-paper transition"
           x-on:click.prevent="document.getElementById('section-{{ $score->section_id }}')?.dispatchEvent(new CustomEvent('open-section')); document.getElementById('section-{{ $score->section_id }}')?.scrollIntoView({behavior: 'smooth', block: 'start'})"
           title="{{ $score->section_name }}: {{ $pct === null ? 'not answered' : number_format((float) $pct, 1).'%' }}">
            <span class="text-sm font-semibold tabular-nums {{ $text }}">{{ $pct === null ? '–' : number_format((float) $pct, 0).'%' }}</span>
            <span class="relative w-full h-24 rounded-lg bg-paper overflow-hidden ring-1 ring-inset ring-line/70" aria-hidden="true">
                <span class="absolute bottom-0 inset-x-0 bg-gradient-to-t {{ $fill }} rounded-t-md transition-all group-hover:brightness-110" style="height: {{ $pct === null ? 0 : max(4, (float) $pct) }}%"></span>
            </span>
            <span class="text-[11px] leading-tight text-ink-soft line-clamp-2">{{ $score->section_name }}</span>
        </a>
    @endforeach
</div>
