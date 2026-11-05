@props(['tone' => 'muted'])
{{-- Status pill. Tones follow the design: ok (olive), warn (amber), bad (red), info (violet), muted (grey). --}}
@php
    $classes = match ($tone) {
        'ok' => 'bg-adm-ok-bg text-adm-ok',
        'warn' => 'bg-adm-warn-bg text-adm-warn',
        'bad' => 'bg-adm-bad-bg text-adm-bad',
        'info' => 'bg-adm-accent-soft text-adm-accent',
        default => 'bg-adm-muted-bg text-adm-soft',
    };
@endphp
<span {{ $attributes->merge(['class' => "adm-pill $classes"]) }}>{{ $slot }}</span>
