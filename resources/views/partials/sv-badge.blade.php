@props(['volume' => null])

@php
    if ($volume === null) {
        $color = '#95a5a6';
        $label = '—';
    } elseif ($volume >= 10000) {
        $color = '#3498db';
        $label = number_format($volume / 1000, 1) . 'K';
    } elseif ($volume >= 1000) {
        $color = '#2ecc71';
        $label = number_format($volume / 1000, 1) . 'K';
    } elseif ($volume >= 100) {
        $color = '#f39c12';
        $label = number_format($volume);
    } else {
        $color = '#95a5a6';
        $label = number_format($volume);
    }
@endphp

<span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-medium text-white" style="background-color: {{ $color }}">{{ $label }}</span>
