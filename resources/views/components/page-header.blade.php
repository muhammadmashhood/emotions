@props(['title', 'subtitle' => null])
<div class="no-print">
    <div class="max-w-screen-2xl mx-auto pt-6 pb-2 px-4 sm:px-6 lg:px-8 flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-2xl font-semibold tracking-tight text-ink">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-sm text-ink-soft mt-1">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
