@props(['dark' => false, 'size' => 'h-9'])
{{-- eMotion logo (public/images). `dark` picks the white version for dark backgrounds. --}}
<img src="{{ asset($dark ? 'images/emotion-logo-white.png' : 'images/emotion-logo.png') }}" alt="eMotion" {{ $attributes->merge(['class' => $size.' w-auto select-none']) }} draggable="false">
