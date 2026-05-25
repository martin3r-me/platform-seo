@props(['value' => 0, 'label' => '', 'size' => 'md'])

@php
    $sizes = [
        'sm' => ['width' => 64, 'height' => 64, 'fontSize' => '13px', 'labelSize' => '8px'],
        'md' => ['width' => 100, 'height' => 100, 'fontSize' => '20px', 'labelSize' => '9px'],
        'lg' => ['width' => 140, 'height' => 140, 'fontSize' => '28px', 'labelSize' => '10px'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $gaugeId = 'gauge-' . uniqid();

    // KWFinder-style color scale
    $gaugeColor = match(true) {
        $value <= 14 => '#2ecc71',  // Easy - green
        $value <= 29 => '#48c774',  // Still easy - light green
        $value <= 39 => '#a3cb38',  // Possible - yellow-green
        $value <= 54 => '#f9ca24',  // Still possible - yellow/orange
        $value <= 69 => '#f39c12',  // Hard - orange
        $value <= 84 => '#e74c3c',  // Very hard - red
        default => '#c0392b',       // Don't do it - dark red
    };

    // KWFinder difficulty label
    $diffLabel = match(true) {
        $value <= 14 => 'Easy',
        $value <= 29 => 'Still easy',
        $value <= 39 => 'Possible',
        $value <= 54 => 'Still possible',
        $value <= 69 => 'Hard',
        $value <= 84 => 'Very hard',
        default => "Don't do it",
    };
@endphp

<div wire:ignore
     x-data x-init="$nextTick(() => {
        if (typeof ApexCharts !== 'undefined') {
            new ApexCharts($el, {
                chart: { type: 'radialBar', height: {{ $s['height'] }}, sparkline: { enabled: true } },
                series: [{{ round($value) }}],
                colors: ['{{ $gaugeColor }}'],
                plotOptions: {
                    radialBar: {
                        startAngle: -135,
                        endAngle: 135,
                        hollow: { size: '58%' },
                        track: { background: '#f0f0f0', strokeWidth: '100%' },
                        dataLabels: {
                            name: { show: {{ $label ? 'true' : 'false' }}, fontSize: '{{ $s['labelSize'] }}', offsetY: 16, color: '#999' },
                            value: { show: true, fontSize: '{{ $s['fontSize'] }}', fontWeight: 700, offsetY: -4, color: '{{ $gaugeColor }}',
                                formatter: function(val) { return Math.round(val); }
                            }
                        }
                    }
                },
                labels: ['{{ $label ?: $diffLabel }}']
            }).render();
        }
    })"
     style="width: {{ $s['width'] }}px; height: {{ $s['height'] }}px;">
</div>
