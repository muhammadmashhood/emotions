@props(['type', 'config', 'height' => 'h-64'])
{{-- Chart.js canvas; the wire:key on the wrapper forces re-creation when the data changes. --}}
<div wire:key="chart-{{ md5(json_encode($config)) }}" wire:ignore class="{{ $height }} relative"
     x-data="{ chart: null, init() { this.chart = new window.Chart(this.$refs.canvas, @js(['type' => $type] + $config)); }, destroy() { this.chart?.destroy(); } }"
     x-init="init()" x-on:livewire:navigating.window="destroy()">
    <canvas x-ref="canvas" role="img" {{ $attributes }}></canvas>
</div>
