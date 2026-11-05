@props(['pct' => null, 'rag' => null, 'green' => 80, 'amber' => 60, 'value' => null])
{{-- Score pill with a band dot: "● 89.8 %" in Good / Watch / Poor colours. Pass `value` to show text instead of the percentage. --}}
@php
    $band = $rag !== null ? \App\Support\ScoreBand::fromRag($rag instanceof \App\Enums\Rag ? $rag : \App\Enums\Rag::tryFrom((string) $rag)) : \App\Support\ScoreBand::of($pct, $green, $amber);
    if ($band->key === null && $pct !== null) { $band = \App\Support\ScoreBand::of($pct, $green, $amber); }
@endphp
<x-ui.pill :tone="$band->tone()" {{ $attributes->merge(['class' => 'gap-1 tabular-nums']) }} title="{{ $band->label() }}">
    @if($band->key)<span class="h-1.5 w-1.5 rounded-full shrink-0" style="background: {{ $band->hex() }}" aria-hidden="true"></span>@endif
    {{ $value ?? ($pct === null ? '–' : number_format((float) $pct, 1).' %') }}
</x-ui.pill>
