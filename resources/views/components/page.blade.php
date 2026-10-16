@props(['wide' => false])
<div {{ $attributes->merge(['class' => 'max-w-screen-2xl mx-auto px-4 sm:px-6 lg:px-8 py-4 space-y-5']) }}>
    {{ $slot }}
</div>
