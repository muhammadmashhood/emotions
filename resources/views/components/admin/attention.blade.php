@props(['tone' => 'muted', 'tag', 'title', 'detail' => null])
{{-- One "needs attention" row: pill, title, explanation and an action on the right. --}}
<li {{ $attributes->merge(['class' => 'flex flex-col items-start sm:flex-row sm:items-center gap-2 sm:gap-3 px-[22px] py-3.5']) }}>
    <x-admin.pill :tone="$tone" class="shrink-0">{{ $tag }}</x-admin.pill>
    <div class="flex-1 min-w-0 w-full">
        <div class="text-[13px] font-medium text-adm-ink">{{ $title }}</div>
        @if($detail)<div class="text-xs text-adm-soft">{{ $detail }}</div>@endif
    </div>
    @isset($action)<div class="shrink-0">{{ $action }}</div>@endisset
</li>
