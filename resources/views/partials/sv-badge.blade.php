@props(['volume' => null])

@php
    if ($volume === null) {
        $color = 'var(--seo-sv-tiny)';
        $label = '—';
    } elseif ($volume >= 10000) {
        $color = 'var(--seo-sv-high)';
        $label = number_format($volume / 1000, 1) . 'K';
    } elseif ($volume >= 1000) {
        $color = 'var(--seo-sv-medium)';
        $label = number_format($volume / 1000, 1) . 'K';
    } elseif ($volume >= 100) {
        $color = 'var(--seo-sv-low)';
        $label = number_format($volume);
    } else {
        $color = 'var(--seo-sv-tiny)';
        $label = number_format($volume);
    }
@endphp

<span class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-medium text-white" style="background-color: {{ $color }}">{{ $label }}</span>
