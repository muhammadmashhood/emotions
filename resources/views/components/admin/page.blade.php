{{-- Admin page frame: design spacing (28px/32px) and 1240px content width. --}}
<div {{ $attributes->merge(['class' => 'px-4 sm:px-8 pt-7 pb-24']) }}>
    <div class="max-w-[1240px] mx-auto flex flex-col gap-5">
        {{ $slot }}
    </div>
</div>
