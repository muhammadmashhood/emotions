@props(['title' => null, 'count' => null])
<section {{ $attributes->merge(['class' => 'bg-white border border-adm-line rounded-2xl overflow-hidden']) }}>
    @if($title || isset($actions))
        <header class="flex items-center justify-between gap-3 px-[22px] pt-[18px] pb-3.5 border-b border-adm-line">
            <h2 class="text-sm font-semibold text-adm-ink">{{ $title }}@if($count !== null)<span class="font-normal text-adm-mute"> · {{ $count }}</span>@endif</h2>
            @isset($actions)<div class="flex items-center gap-2 text-[12.5px]">{{ $actions }}</div>@endisset
        </header>
    @endif
    {{ $slot }}
</section>
