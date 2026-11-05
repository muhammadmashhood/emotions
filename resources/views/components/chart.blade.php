@props(['type', 'config', 'height' => 'h-64', 'centerText' => null, 'guides' => []])
{{--
    Chart.js canvas; the wire:key on the wrapper forces re-creation when the data changes.
    `centerText` = ['value' => '38%', 'label' => 'rated Good'] draws a doughnut centre label.
    `guides` = [['y' => 80, 'color' => '#C2B700'], …] draws dashed horizontal band lines.
--}}
<div wire:key="chart-{{ md5(json_encode($config).json_encode($centerText).json_encode($guides)) }}" wire:ignore class="{{ $height }} relative"
     x-data="{
        chart: null,
        init() {
            const cfg = @js(['type' => $type] + $config);
            const center = @js($centerText);
            const guides = @js($guides);
            const font = 'Poppins, system-ui, sans-serif';
            window.Chart.defaults.font.family = font;
            window.Chart.defaults.color = '#7A7277';
            cfg.plugins = [];
            if (center) cfg.plugins.push({
                id: 'centerText',
                afterDraw(chart) {
                    const { ctx, chartArea } = chart;
                    if (!chartArea) return;
                    ctx.save();
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    const x = (chartArea.left + chartArea.right) / 2, y = (chartArea.top + chartArea.bottom) / 2;
                    ctx.fillStyle = '#2B1F26';
                    ctx.font = '600 24px ' + font;
                    ctx.fillText(center.value, x, y - 7);
                    ctx.fillStyle = '#7A7277';
                    ctx.font = '400 11px ' + font;
                    ctx.fillText(center.label, x, y + 15);
                    ctx.restore();
                }
            });
            if (guides.length) cfg.plugins.push({
                id: 'bandGuides',
                beforeDatasetsDraw(chart) {
                    const { ctx, chartArea, scales } = chart;
                    if (!chartArea || !scales.y) return;
                    ctx.save();
                    ctx.setLineDash([4, 4]);
                    ctx.lineWidth = 1;
                    guides.forEach(g => {
                        const y = scales.y.getPixelForValue(g.y);
                        if (y < chartArea.top || y > chartArea.bottom) return;
                        ctx.strokeStyle = g.color;
                        ctx.beginPath(); ctx.moveTo(chartArea.left, y); ctx.lineTo(chartArea.right, y); ctx.stroke();
                    });
                    ctx.restore();
                }
            });
            this.chart = new window.Chart(this.$refs.canvas, cfg);
        },
        destroy() { this.chart?.destroy(); }
     }"
     x-on:livewire:navigating.window="destroy()">
    <canvas x-ref="canvas" role="img" {{ $attributes }}></canvas>
</div>
