@props(['type' => 'info'])
@php
    $classes = match ($type) {
        'success' => 'border-rag-green/40 bg-rag-green/10 text-rag-green',
        'warning' => 'border-rag-amber/40 bg-rag-amber/10 text-rag-amber',
        'danger', 'error' => 'border-rag-red/40 bg-rag-red/10 text-rag-red',
        default => 'border-ink/30 bg-ink/5 text-ink',
    };
@endphp
<div {{ $attributes->merge(['class' => "rounded-md border px-4 py-2 text-sm $classes"]) }} role="alert">{{ $slot }}</div>
