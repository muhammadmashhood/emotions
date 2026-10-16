@props(['type', 'config', 'height' => 'h-64', 'centerText' => null])
{{-- Chart.js canvas; the wire:key on the wrapper forces re-creation when the data changes. --}}
<div wire:key="chart-{{ md5(json_encode($config).json_encode($centerText)) }}" wire:ignore class="{{ $height }} relative"
     x-data="{
        chart: null,
        init() {
            const cfg = @js(['type' => $type] + $config);
            const center = @js($centerText);
            cfg.plugins = center ? [{
                id: 'centerText',
                afterDraw(chart) {
                    const { ctx, chartArea } = chart;
                    if (!chartArea) return;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillStyle = '#123A4A';
                    ctx.font = '600 22px \'Public Sans\', system-ui, sans-serif';
                    const x = (chartArea.left + chartArea.right) / 2, y = (chartArea.top + chartArea.bottom) / 2;
                    ctx.fillText(center.value, x, y - 6);
                    ctx.fillStyle = '#4E6875';
                    ctx.font = '500 11px \'Public Sans\', system-ui, sans-serif';
                    ctx.fillText(center.label, x, y + 14);
                    ctx.restore();
                }
            }] : [];
            this.chart = new window.Chart(this.$refs.canvas, cfg);
        },
        destroy() { this.chart?.destroy(); }
     }"
     x-on:livewire:navigating.window="destroy()">
    <canvas x-ref="canvas" role="img" {{ $attributes }}></canvas>
</div>
