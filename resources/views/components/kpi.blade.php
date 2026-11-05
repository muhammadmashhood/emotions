@props(['label', 'value', 'unit' => null, 'delta' => null, 'deltaLabel' => null, 'note' => null, 'tone' => null])
@php
    $valueClass = match ($tone) { 'green' => 'text-em-ok', 'amber' => 'text-em-warn', 'red' => 'text-em-bad', default => 'text-em-ink' };
@endphp
<div {{ $attributes->merge(['class' => 'panel px-[18px] py-4 flex flex-col gap-1 min-w-0']) }}>
    <span class="text-xs text-em-soft">{{ $label }}</span>
    <span class="flex items-baseline gap-1 flex-wrap">
        <span class="kpi-value {{ $valueClass }}">{{ $value }}</span>
        @if($unit)<span class="text-xs text-em-mute">{{ $unit }}</span>@endif
    </span>
    @if($delta !== null)
        <span class="text-[11.5px] font-medium {{ $delta > 0 ? 'text-em-ok' : ($delta < 0 ? 'text-em-bad' : 'text-em-mute') }}">
            <span aria-hidden="true">{{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '▶') }}</span> {{ $delta > 0 ? '+' : '' }}{{ number_format($delta, 1) }} {{ $deltaLabel }}
        </span>
    @elseif($note)
        <span class="text-[11.5px] text-em-mute">{{ $note }}</span>
    @endif
</div>
