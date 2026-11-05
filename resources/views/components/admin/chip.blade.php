@props(['active' => false])
<button type="button" {{ $attributes->merge(['class' => 'px-3 py-1.5 rounded-full border text-[12.5px] transition-colors '.($active ? 'bg-adm-ink border-adm-ink text-white' : 'bg-white border-adm-field text-adm-ink hover:border-adm-hover')]) }} @if($active) aria-pressed="true" @else aria-pressed="false" @endif>{{ $slot }}</button>
