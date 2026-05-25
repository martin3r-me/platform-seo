@props(['value' => null])

@php
    // KWFinder-style difficulty color scale
    if ($value === null) {
        $bg = null;
        $color = 'bg-gray-100 text-gray-400';
        $label = '—';
    } elseif ($value <= 14) {
        $bg = '#2ecc71'; $color = 'text-white'; $label = $value;
    } elseif ($value <= 29) {
        $bg = '#48c774'; $color = 'text-white'; $label = $value;
    } elseif ($value <= 39) {
        $bg = '#a3cb38'; $color = 'text-white'; $label = $value;
    } elseif ($value <= 54) {
        $bg = '#f9ca24'; $color = 'text-gray-900'; $label = $value;
    } elseif ($value <= 69) {
        $bg = '#f39c12'; $color = 'text-white'; $label = $value;
    } elseif ($value <= 84) {
        $bg = '#e74c3c'; $color = 'text-white'; $label = $value;
    } else {
        $bg = '#c0392b'; $color = 'text-white'; $label = $value;
    }
@endphp

@if($bg === null)
    <span class="inline-flex items-center justify-center w-8 h-5 rounded text-[11px] font-semibold {{ $color }}">{{ $label }}</span>
@else
    <span class="inline-flex items-center justify-center w-8 h-5 rounded text-[11px] font-semibold {{ $color }}" style="background-color: {{ $bg }}">{{ $label }}</span>
@endif
