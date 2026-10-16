@props(['tabs' => [], 'current'])
<nav class="flex gap-1 border-b border-line overflow-x-auto no-print" aria-label="Tabs">
    @foreach($tabs as $key => $label)
        <button type="button" wire:click="$set('tab', '{{ $key }}')"
            class="px-3 py-2 text-sm font-medium border-b-2 -mb-px whitespace-nowrap {{ $current === $key ? 'border-ink text-ink' : 'border-transparent text-ink-soft hover:text-ink' }}"
            @if($current === $key) aria-current="page" @endif>{{ $label }}</button>
    @endforeach
</nav>
