@props(['pct' => null])
{{-- Score pill: Good ≥ 80, Watch ≥ 60, Poor below (same thresholds as the RAG bands). --}}
@php
    $tone = $pct === null ? 'muted' : ((float) $pct >= 80 ? 'ok' : ((float) $pct >= 60 ? 'warn' : 'bad'));
    $title = ['ok' => 'Good', 'warn' => 'Watch', 'bad' => 'Poor', 'muted' => 'No data'][$tone];
@endphp
<x-ui.pill :tone="$tone" {{ $attributes->merge(['class' => 'tabular-nums']) }} title="{{ $title }}">{{ $pct === null ? 'No data' : number_format((float) $pct, 1).'%' }}</x-ui.pill>
