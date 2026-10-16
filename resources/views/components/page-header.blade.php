@props(['title', 'subtitle' => null])
<div class="bg-white border-b border-line no-print">
    <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-semibold text-ink">{{ $title }}</h1>
            @if($subtitle)
                <p class="text-sm text-ink-soft mt-0.5">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
</div>
