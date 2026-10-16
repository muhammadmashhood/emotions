@props(['title' => null, 'padding' => true])
<section {{ $attributes->merge(['class' => 'panel']) }}>
    @if($title || isset($actions))
        <header class="flex items-center justify-between gap-3 px-4 py-3 border-b border-line">
            <h2 class="text-sm font-semibold text-ink">{{ $title }}</h2>
            @isset($actions)<div class="flex items-center gap-2">{{ $actions }}</div>@endisset
        </header>
    @endif
    <div class="{{ $padding ? 'p-4' : '' }}">{{ $slot }}</div>
</section>
