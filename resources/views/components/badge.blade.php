@props(['variant' => 'neutral'])
@php
    $classes = match ($variant) {
        'green', 'success' => 'bg-rag-green/10 text-rag-green border-rag-green/30',
        'amber', 'warning' => 'bg-rag-amber/10 text-rag-amber border-rag-amber/30',
        'red', 'danger' => 'bg-rag-red/10 text-rag-red border-rag-red/30',
        'ink', 'info' => 'bg-ink/10 text-ink border-ink/30',
        default => 'bg-paper text-ink-soft border-line',
    };
@endphp
<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium whitespace-nowrap $classes"]) }}>{{ $slot }}</span>
