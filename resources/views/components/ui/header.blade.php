@props(['title', 'subtitle' => null, 'eyebrow' => 'eMotion admin', 'back' => null, 'backLabel' => null])
{{-- Page header from the eMotion Admin design: eyebrow or back link, 26px title, muted subtitle, actions on the right. --}}
<div class="flex flex-wrap items-end justify-between gap-4 no-print">
    <div class="flex items-center gap-3.5 min-w-0">
        {{ $leading ?? '' }}
        <div class="flex flex-col gap-1 min-w-0">
            @if($back)
                <a href="{{ $back }}" wire:navigate class="text-xs text-em-accent hover:underline w-fit">← {{ $backLabel }}</a>
            @elseif(isset($eyebrowSlot))
                <div class="flex flex-wrap items-center gap-2 text-xs text-em-mute">{{ $eyebrowSlot }}</div>
            @elseif($eyebrow)
                <div class="text-xs text-em-mute">{{ $eyebrow }}</div>
            @endif
            <h1 class="text-[26px] leading-tight font-semibold tracking-[-0.015em] text-em-ink">{{ $title }}</h1>
            @if($subtitle)
                <div class="text-[13px] text-em-soft">{{ $subtitle }}</div>
            @endif
        </div>
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
