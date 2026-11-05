@props(['active' => false])
<button type="button" {{ $attributes->merge(['class' => 'px-3 py-1.5 rounded-full border text-[12.5px] transition-colors '.($active ? 'bg-em-ink border-em-ink text-white' : 'bg-white border-em-field text-em-ink hover:border-em-hover')]) }} @if($active) aria-pressed="true" @else aria-pressed="false" @endif>{{ $slot }}</button>
