@props(['value' => null])

@php
    if ($value === null) {
        $color = 'bg-gray-100 text-gray-400';
        $bg = null;
        $label = '—';
    } elseif ($value < 15) {
        $color = 'text-white';
        $bg = '#2ecc71';
        $label = $value;
    } elseif ($value < 30) {
        $color = 'text-white';
        $bg = '#a3cb38';
        $label = $value;
    } elseif ($value < 50) {
        $color = 'text-gray-900';
        $bg = '#f9ca24';
        $label = $value;
    } elseif ($value < 65) {
        $color = 'text-white';
        $bg = '#f39c12';
        $label = $value;
    } elseif ($value < 85) {
        $color = 'text-white';
        $bg = '#e74c3c';
        $label = $value;
    } else {
        $color = 'text-white';
        $bg = '#8e44ad';
        $label = $value;
    }
@endphp

@if($bg === null)
    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}">{{ $label }}</span>
@else
    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium {{ $color }}" style="background-color: {{ $bg }}">{{ $label }}</span>
@endif
