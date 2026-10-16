@props(['pct' => null, 'rag' => null, 'size' => 'sm'])
@php
    $ragValue = $rag instanceof \App\Enums\Rag ? $rag : ($rag ? \App\Enums\Rag::tryFrom((string) $rag) : null);
    if ($ragValue === null && $pct !== null) {
        $ragValue = \App\Enums\Rag::fromPct((float) $pct, 80, 60);
    }
    $color = match ($ragValue) {
        \App\Enums\Rag::Green => 'bg-rag-green/10 text-rag-green ring-rag-green/30',
        \App\Enums\Rag::Amber => 'bg-rag-amber/10 text-rag-amber ring-rag-amber/30',
        \App\Enums\Rag::Red => 'bg-rag-red/10 text-rag-red ring-rag-red/30',
        default => 'bg-rag-neutral/15 text-ink-soft ring-line',
    };
    $sizeClass = $size === 'lg' ? 'px-4 py-2 text-2xl font-semibold' : 'px-2 py-0.5 text-xs font-semibold';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full ring-1 ring-inset tabular-nums $color $sizeClass"]) }}
      title="{{ $ragValue?->label() ?? 'No score' }}">
    @if($ragValue)<span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>@endif
    {{ $pct === null ? '–' : number_format((float) $pct, 1).'%' }}
</span>
