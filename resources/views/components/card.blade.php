@props(['title' => null, 'padding' => true, 'subtitle' => null])
<section {{ $attributes->merge(['class' => 'panel overflow-hidden']) }}>
    @if($title || isset($actions))
        <header class="flex items-start justify-between gap-3 px-5 pt-[18px] pb-3">
            <div>
                <h2 class="text-sm font-semibold text-em-ink">{{ $title }}</h2>
                @if($subtitle)<p class="text-xs text-em-soft mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex items-center gap-2 text-xs text-em-soft">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div class="{{ $padding ? 'px-5 pb-5 '.($title ? '' : 'pt-5') : '' }}">{{ $slot }}</div>
</section>
