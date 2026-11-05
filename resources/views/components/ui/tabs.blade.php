@props(['tabs' => [], 'current'])
{{-- Underlined tabs with optional counts: ['key' => ['label' => 'Forms', 'count' => 5]] or ['key' => 'Label']. --}}
<nav class="flex gap-1 border-b border-em-line overflow-x-auto no-print" aria-label="Tabs">
    @foreach($tabs as $key => $tab)
        @php $label = is_array($tab) ? $tab['label'] : $tab; $count = is_array($tab) ? ($tab['count'] ?? null) : null; @endphp
        <button type="button" wire:click="$set('tab', '{{ $key }}')"
            class="px-3.5 py-2.5 text-[13px] border-b-2 -mb-px whitespace-nowrap {{ $current === $key ? 'border-em-ink text-em-ink font-semibold' : 'border-transparent text-em-soft hover:text-em-ink' }}"
            @if($current === $key) aria-current="page" @endif>{{ $label }}@if($count !== null)<span class="ml-1.5 font-normal text-em-mute">{{ $count }}</span>@endif</button>
    @endforeach
</nav>
