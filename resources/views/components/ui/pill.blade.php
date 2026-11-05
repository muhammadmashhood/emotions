@props(['tone' => 'muted'])
{{-- Status pill. Tones follow the design: ok (olive), warn (amber), bad (red), info (violet), muted (grey). --}}
@php
    $classes = match ($tone) {
        'ok' => 'bg-em-ok-bg text-em-ok',
        'warn' => 'bg-em-warn-bg text-em-warn',
        'bad' => 'bg-em-bad-bg text-em-bad',
        'info' => 'bg-em-accent-soft text-em-accent',
        default => 'bg-em-muted-bg text-em-soft',
    };
@endphp
<span {{ $attributes->merge(['class' => "em-pill $classes"]) }}>{{ $slot }}</span>
