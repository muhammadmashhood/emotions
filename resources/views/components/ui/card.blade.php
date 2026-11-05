@props(['title' => null, 'count' => null, 'subtitle' => null, 'divider' => true])
{{-- Card with a title row (optional count, subtitle and actions). `divider` draws the line under the title (admin lists). --}}
<section {{ $attributes->merge(['class' => 'bg-white border border-em-line rounded-2xl overflow-hidden']) }}>
    @if($title || isset($actions))
        <header class="flex items-start justify-between gap-3 px-[22px] pt-[18px] {{ $divider ? 'pb-3.5 border-b border-em-line' : 'pb-1' }}">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-em-ink">{{ $title }}@if($count !== null)<span class="font-normal text-em-mute"> · {{ $count }}</span>@endif</h2>
                @if($subtitle)<p class="text-xs text-em-soft mt-0.5">{{ $subtitle }}</p>@endif
            </div>
            @isset($actions)<div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[12.5px] pt-px">{{ $actions }}</div>@endisset
        </header>
    @endif
    {{ $slot }}
</section>
