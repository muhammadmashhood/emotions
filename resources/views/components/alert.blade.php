@props(['type' => 'info'])
@php
    $classes = match ($type) {
        'success' => 'border-em-ok/20 bg-em-ok-bg text-em-ok',
        'warning' => 'border-em-warn/20 bg-em-warn-bg text-em-warn',
        'danger', 'error' => 'border-em-bad/20 bg-em-bad-bg text-em-bad',
        default => 'border-em-accent/20 bg-em-accent-soft text-em-accent',
    };
@endphp
<div {{ $attributes->merge(['class' => "rounded-xl border px-4 py-2.5 text-[13px] $classes"]) }} role="alert">{{ $slot }}</div>
