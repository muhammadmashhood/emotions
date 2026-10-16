@props(['label', 'value', 'unit' => null, 'delta' => null, 'deltaLabel' => null, 'note' => null, 'tone' => null])
@php
    $valueClass = match ($tone) { 'green' => 'text-rag-green', 'amber' => 'text-rag-amber', 'red' => 'text-rag-red', default => 'text-ink' };
@endphp
<div {{ $attributes->merge(['class' => 'panel px-5 py-4 flex flex-col gap-1 min-w-0']) }}>
    <span class="eyebrow">{{ $label }}</span>
    <span class="flex items-baseline gap-1 flex-wrap">
        <span class="kpi-value {{ $valueClass }}">{{ $value }}</span>
        @if($unit)<span class="text-sm text-ink-soft">{{ $unit }}</span>@endif
    </span>
    @if($delta !== null)
        <span class="text-xs font-medium {{ $delta > 0 ? 'text-rag-green' : ($delta < 0 ? 'text-rag-red' : 'text-ink-soft') }}">
            <span aria-hidden="true">{{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '▶') }}</span> {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1) }} {{ $deltaLabel }}
        </span>
    @elseif($note)
        <span class="text-xs text-ink-soft">{{ $note }}</span>
    @endif
</div>
