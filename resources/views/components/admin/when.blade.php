@props(['at' => null])
{{-- "Today, 15:52" / "Yesterday, 18:30" / "28 Oct 2026, 09:14", as in the design. --}}
@if($at)
    <time datetime="{{ $at->toIso8601String() }}" title="{{ $at->format('d M Y H:i:s') }}" {{ $attributes }}>{{ $at->isToday() ? 'Today, '.$at->format('H:i') : ($at->isYesterday() ? 'Yesterday, '.$at->format('H:i') : $at->format('j M Y, H:i')) }}</time>
@else
    <span {{ $attributes->merge(['class' => 'text-adm-mute']) }}>In progress</span>
@endif
