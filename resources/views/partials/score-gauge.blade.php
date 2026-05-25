@props(['value' => 0, 'label' => '', 'size' => 'md'])

@php
    $sizes = [
        'sm' => ['width' => 80, 'height' => 80, 'fontSize' => '14px'],
        'md' => ['width' => 120, 'height' => 120, 'fontSize' => '18px'],
        'lg' => ['width' => 160, 'height' => 160, 'fontSize' => '22px'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $gaugeId = 'gauge-' . uniqid();

    $gaugeColor = match(true) {
        $value >= 80 => '#2ecc71',
        $value >= 60 => '#a3cb38',
        $value >= 40 => '#f9ca24',
        $value >= 20 => '#f39c12',
        default => '#e74c3c',
    };
@endphp

<div id="{{ $gaugeId }}" style="width: {{ $s['width'] }}px; height: {{ $s['height'] }}px;" wire:ignore
     x-data x-init="$nextTick(() => {
        if (typeof ApexCharts !== 'undefined') {
            new ApexCharts($el, {
                chart: { type: 'radialBar', height: {{ $s['height'] }}, sparkline: { enabled: true } },
                series: [{{ round($value) }}],
                colors: ['{{ $gaugeColor }}'],
                plotOptions: {
                    radialBar: {
                        hollow: { size: '55%' },
                        track: { background: '#f3f4f6' },
                        dataLabels: {
                            name: { show: @json($label !== ''), fontSize: '10px', offsetY: 14, color: '#9ca3af' },
                            value: { show: true, fontSize: '{{ $s['fontSize'] }}', fontWeight: 600, offsetY: -2, color: '#1f2937',
                                formatter: function(val) { return Math.round(val) + '%'; }
                            }
                        }
                    }
                },
                labels: ['{{ $label }}']
            }).render();
        }
    })">
</div>
