@props(['data' => [], 'color' => '#6366f1', 'height' => 40, 'type' => 'area'])

@php
    $jsonData = json_encode(array_values($data));
@endphp

<div wire:ignore
     x-data x-init="$nextTick(() => {
        if (typeof ApexCharts !== 'undefined') {
            new ApexCharts($el, {
                chart: { type: '{{ $type }}', height: {{ $height }}, sparkline: { enabled: true } },
                series: [{ data: {{ $jsonData }} }],
                colors: ['{{ $color }}'],
                stroke: { width: {{ $type === 'area' ? 2 : 0 }}, curve: 'smooth' },
                fill: { type: '{{ $type === 'area' ? 'gradient' : 'solid' }}', gradient: { opacityFrom: 0.3, opacityTo: 0.05 } },
                plotOptions: { bar: { borderRadius: 2, columnWidth: '65%' } },
                tooltip: { enabled: true, y: { formatter: function(val) { return val ? val.toLocaleString() : '0'; } } }
            }).render();
        }
    })"
     style="height: {{ $height }}px; width: 100%;">
</div>
