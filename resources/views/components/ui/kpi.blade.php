@props(['label', 'value', 'note' => null, 'tone' => null, 'href' => null])
@php
    $valueClass = match ($tone) { 'warn' => 'text-em-warn', 'bad' => 'text-em-bad', 'ok' => 'text-em-ok', default => 'text-em-ink' };
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->merge(['class' => 'bg-white border border-em-line rounded-2xl px-[18px] py-4 flex flex-col gap-1 min-w-0 '.($href ? 'hover:border-[#D9D3D9] transition-colors' : '')]) }}>
    <span class="text-xs text-em-soft">{{ $label }}</span>
    <span class="text-[28px] font-semibold tracking-[-0.02em] leading-[1.15] tabular-nums {{ $valueClass }}">{{ $value }}</span>
    @if($note)<span class="text-[11.5px] text-em-mute">{{ $note }}</span>@endif
</{{ $tag }}>
