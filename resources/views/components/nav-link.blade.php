@props(['active'])
@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-3 py-2 rounded-md text-sm font-medium bg-paper text-ink'
            : 'inline-flex items-center px-3 py-2 rounded-md text-sm font-medium text-ink-soft hover:text-ink hover:bg-paper transition';
@endphp
<a {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
