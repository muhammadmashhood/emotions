@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'rounded-[10px] border border-white/15 bg-white/[0.06] px-3.5 py-2.5 text-[12.5px] text-[#E6E1E4]']) }} role="status">
        {{ $status }}
    </div>
@endif
