@props(['pct' => null, 'rag' => null, 'size' => 'sm'])
{{-- Kept for existing call sites; renders the design's band pill. --}}
<x-ui.band :pct="$pct" :rag="$rag" {{ $attributes->class([$size === 'lg' ? 'px-3 py-1.5 text-lg' : '']) }} />
