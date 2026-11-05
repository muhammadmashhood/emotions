@props(['title' => 'Nothing here yet', 'message' => null])
<div {{ $attributes->merge(['class' => 'panel p-10 text-center']) }}>
    <p class="font-medium text-em-ink">{{ $title }}</p>
    @if($message)
        <p class="text-em-soft text-[13px] mt-1">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
