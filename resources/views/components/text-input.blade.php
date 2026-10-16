@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-line focus:border-ink focus:ring-ink rounded-md shadow-sm']) }}>
