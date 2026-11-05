@props(['name', 'logo' => null, 'size' => 'md', 'seed' => null])
{{-- Initial tile in one of the design's brand colours (stable per hospital), or the uploaded logo. --}}
@php
    $palette = ['#7470F0', '#3E8E7E', '#D9577A', '#E3A048', '#2B1F26', '#9A9400'];
    $color = $palette[abs(crc32((string) ($seed ?? $name))) % count($palette)];
    $box = $size === 'lg' ? 'h-12 w-12 rounded-xl text-xl' : 'h-10 w-10 rounded-[10px] text-base';
@endphp
@if($logo)
    <img src="{{ Storage::disk('public')->url($logo) }}" alt="" {{ $attributes->merge(['class' => "$box object-contain bg-white border border-em-line p-1 shrink-0"]) }}>
@else
    <span {{ $attributes->merge(['class' => "$box flex items-center justify-center text-white font-semibold shrink-0"]) }} style="background: {{ $color }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>
@endif
