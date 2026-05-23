@props(['position' => null, 'change' => null])

@php
    if ($position === null) {
        $color = 'var(--seo-pos-none)';
        $label = '—';
    } elseif ($position <= 3) {
        $color = 'var(--seo-pos-top3)';
        $label = $position;
    } elseif ($position <= 10) {
        $color = 'var(--seo-pos-top10)';
        $label = $position;
    } elseif ($position <= 20) {
        $color = 'var(--seo-pos-top20)';
        $label = $position;
    } elseif ($position <= 50) {
        $color = 'var(--seo-pos-top50)';
        $label = $position;
    } else {
        $color = 'var(--seo-pos-top100)';
        $label = $position;
    }
@endphp

<span class="inline-flex items-center gap-1">
    <span class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded text-xs font-semibold text-white" style="background-color: {{ $color }}">{{ $label }}</span>
    @if($change !== null && $change !== 0)
        @if($change > 0)
            <span class="text-xs font-medium text-green-600 flex items-center gap-0.5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                {{ $change }}
            </span>
        @else
            <span class="text-xs font-medium text-red-600 flex items-center gap-0.5">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                {{ abs($change) }}
            </span>
        @endif
    @endif
</span>
