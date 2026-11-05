@props(['variant' => 'neutral'])
@php
    $tone = match ($variant) {
        'green', 'success' => 'ok',
        'amber', 'warning' => 'warn',
        'red', 'danger' => 'bad',
        'ink', 'info' => 'info',
        default => 'muted',
    };
@endphp
<x-ui.pill :tone="$tone" {{ $attributes }}>{{ $slot }}</x-ui.pill>
