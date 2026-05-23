@props(['data' => [], 'color' => '#6366f1', 'height' => 40])

@php
    $sparkId = 'spark-' . uniqid();
    $jsonData = json_encode(array_values($data));
@endphp

<div id="{{ $sparkId }}" style="height: {{ $height }}px; width: 100%;" wire:ignore></div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof ApexCharts !== 'undefined') {
            new ApexCharts(document.querySelector('#{{ $sparkId }}'), {
                chart: { type: 'area', height: {{ $height }}, sparkline: { enabled: true } },
                series: [{ data: {!! $jsonData !!} }],
                colors: ['{{ $color }}'],
                stroke: { width: 2, curve: 'smooth' },
                fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0.05 } },
                tooltip: { enabled: false }
            }).render();
        }
    });
</script>
