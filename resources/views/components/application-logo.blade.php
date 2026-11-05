@props(['dark' => false])
{{-- eMotion mark: the "e" tile and the MOTION wordmark. --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="h-9 w-9 rounded-[10px] flex items-center justify-center text-2xl font-medium leading-none pb-[3px] {{ $dark ? 'bg-white text-em-ink' : 'bg-em-ink text-white' }}" aria-hidden="true">e</span>
    <span class="text-[19px] font-light tracking-[0.09em]">MOTION</span>
</span>
