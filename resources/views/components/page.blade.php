@props(['wide' => false])
<div {{ $attributes->merge(['class' => 'max-w-[1320px] mx-auto px-4 sm:px-8 py-5 flex flex-col gap-5']) }}>
    {{ $slot }}
</div>
