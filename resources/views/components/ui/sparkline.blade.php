@props(['values' => [], 'color' => '#7470F0', 'height' => 32])
{{-- Tiny inline trend line; nulls are skipped. --}}
@php
    $points = collect($values)->values()->map(fn ($v, $i) => [$i, $v])->filter(fn ($p) => $p[1] !== null)->values();
    $n = max(1, count($values) - 1);
    $min = $points->min(fn ($p) => $p[1]); $max = $points->max(fn ($p) => $p[1]);
    $span = ($max - $min) ?: 1;
    $path = $points->map(fn ($p) => round(100 * $p[0] / $n, 2).','.round(($height - 3) - (($p[1] - $min) / $span) * ($height - 6), 2))->implode(' ');
@endphp
@if($points->count() >= 2)
    <svg viewBox="0 0 100 {{ $height }}" preserveAspectRatio="none" {{ $attributes->merge(['class' => 'w-full']) }} style="height: {{ $height }}px" aria-hidden="true">
        <polyline points="{{ $path }}" fill="none" stroke="{{ $color }}" stroke-width="1.5" vector-effect="non-scaling-stroke" stroke-linejoin="round" stroke-linecap="round"/>
    </svg>
@endif
