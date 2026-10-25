{{-- Kunden-Tor · Sichtbarkeit / Themen: Keyword-Cluster mit Abdeckung (wie sichtbar die Website im Thema ist). Daten: $d. --}}
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
@endphp
<div class="gp-wrap py-6">
    <p class="mb-4 max-w-3xl text-sm text-[color:var(--nx-muted)]">Themen fassen zusammengehörige Suchbegriffe zusammen. Die Sichtbarkeit zeigt, wie gut Sie im Thema gefunden werden: 100 % hieße Platz 1 für alle Begriffe.</p>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 2xl:grid-cols-3">
        @foreach ($d['clusters'] as $c)
            <div class="gp-card p-4">
                <div class="flex items-start justify-between gap-3">
                    <span class="font-medium leading-snug">{{ $c['name'] }}</span>
                    <span class="shrink-0 text-lg font-semibold tabular-nums">{{ $c['pct'] }} %</span>
                </div>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                    <div class="h-full rounded-full" style="width: {{ min(100, $c['pct']) }}%; background: {{ $c['pct'] >= 50 ? 'var(--nx-success)' : ($c['pct'] >= 20 ? 'var(--nx-info)' : 'var(--nx-warning)') }}"></div>
                </div>
                <div class="mt-2 text-xs text-[color:var(--nx-faint)]">{{ $c['ist'] }} von {{ $c['soll'] }} Begriffen gefunden · {{ $n($c['volume']) }} Suchen / Monat</div>
            </div>
        @endforeach
    </div>
</div>
