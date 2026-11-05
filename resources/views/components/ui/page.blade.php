@props(['wide' => false])
{{-- Page frame: design spacing (28px/32px); 1240px content for admin pages, 1320px for hospital dashboards. --}}
<div {{ $attributes->merge(['class' => 'px-4 sm:px-8 pt-7 pb-24']) }}>
    <div class="{{ $wide ? 'max-w-[1320px]' : 'max-w-[1240px]' }} mx-auto flex flex-col gap-5">
        {{ $slot }}
    </div>
</div>
