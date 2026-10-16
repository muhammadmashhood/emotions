@props(['pct' => null, 'rag' => null, 'size' => 'sm'])
@php
    $ragValue = $rag instanceof \App\Enums\Rag ? $rag : ($rag ? \App\Enums\Rag::tryFrom((string) $rag) : null);
    if ($ragValue === null && $pct !== null) {
        $ragValue = \App\Enums\Rag::fromPct((float) $pct, 80, 60);
    }
    $color = match ($ragValue) {
        \App\Enums\Rag::Green => 'bg-rag-green text-white',
        \App\Enums\Rag::Amber => 'bg-rag-amber text-white',
        \App\Enums\Rag::Red => 'bg-rag-red text-white',
        default => 'bg-rag-neutral/30 text-ink-soft',
    };
    $sizeClass = $size === 'lg' ? 'px-4 py-2 text-2xl font-semibold' : 'px-2 py-0.5 text-xs font-semibold';
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full tabular-nums $color $sizeClass"]) }}
      title="{{ $ragValue?->label() ?? 'No score' }}">
    {{ $pct === null ? '–' : number_format((float) $pct, 1).'%' }}
</span>
