@props(['title' => 'Nothing here yet', 'message' => null])
<div {{ $attributes->merge(['class' => 'panel p-8 text-center']) }}>
    <p class="text-ink font-medium">{{ $title }}</p>
    @if($message)
        <p class="text-ink-soft text-sm mt-1">{{ $message }}</p>
    @endif
    @isset($action)
        <div class="mt-4">{{ $action }}</div>
    @endisset
</div>
