@props(['title', 'subtitle' => null])
{{-- Legacy page header (used outside a page frame); same typography as <x-ui.header>. --}}
<div class="px-4 sm:px-8 pt-7 no-print">
    <div class="max-w-[1320px] mx-auto flex flex-wrap items-end justify-between gap-4">
        <div class="flex flex-col gap-1 min-w-0">
            <h1 class="text-[26px] leading-tight font-semibold tracking-[-0.015em] text-em-ink">{{ $title }}</h1>
            @if($subtitle)<div class="text-[13px] text-em-soft">{{ $subtitle }}</div>@endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
