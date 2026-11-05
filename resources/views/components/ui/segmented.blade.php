@props(['items' => [], 'current'])
{{-- Dark segmented switch between sibling pages (e.g. Webhook deliveries | Audit log). --}}
<div class="inline-flex p-[3px] gap-0.5 bg-white border border-em-line rounded-xl w-fit">
    @foreach($items as $key => $item)
        <a href="{{ $item['href'] }}" wire:navigate class="px-3.5 py-1.5 rounded-[9px] text-[13px] {{ $current === $key ? 'bg-em-ink text-white font-semibold' : 'text-em-soft hover:text-em-ink' }}" @if($current === $key) aria-current="page" @endif>{{ $item['label'] }}</a>
    @endforeach
</div>
