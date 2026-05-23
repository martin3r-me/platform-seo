@props(['value' => null])

@php
    if ($value === null) {
        $color = 'bg-gray-100 text-gray-400';
        $label = '—';
    } elseif ($value < 15) {
        $color = 'text-white';
        $bg = 'var(--seo-kd-easy)';
        $label = $value;
    } elseif ($value < 30) {
        $color = 'text-white';
        $bg = 'var(--seo-kd-possible)';
        $label = $value;
    } elseif ($value < 50) {
        $color = 'text-gray-900';
        $bg = 'var(--seo-kd-moderate)';
        $label = $value;
    } elseif ($value < 65) {
        $color = 'text-white';
        $bg = 'var(--seo-kd-difficult)';
        $label = $value;
    } elseif ($value < 85) {
        $color = 'text-white';
        $bg = 'var(--seo-kd-hard)';
        $label = $value;
    } else {
        $color = 'text-white';
        $bg = 'var(--seo-kd-extreme)';
        $label = $value;
    }
@endphp

@if($value === null)
    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">{{ $label }}</span>
@else
    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}" style="background-color: {{ $bg }}">{{ $label }}</span>
@endif
