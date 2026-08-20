@props(['clusters' => null, 'coverage' => null, 'onlyClusters' => false])

{{--
    Gemeinsames Scope-Panel — Ordnungsgrad + Durchdringung je Cluster (NX-Sprache).
    Reine Lesart einer URL-Menge (URL+Unterseiten / Portfolio / Liste), damit
    sich der Nutzer an EINE Sprache gewöhnt. Daten: Platform\Seo\Services\
    SeoScopeMetrics. Aktionen (clustern etc.) bleiben scope-spezifisch außerhalb.
--}}
@php
    $cov = $coverage ?? ['pct' => 0, 'unclustered_pct' => 0, 'total' => 0, 'clustered' => 0, 'ranking' => 0];
    $cls = $clusters ?? collect();
    $barColor = fn ($pct) => $pct >= 70 ? 'var(--nx-success)' : ($pct >= 30 ? 'var(--nx-warning)' : 'var(--nx-faint)');
@endphp

@if(! $onlyClusters && ($cov['total'] ?? 0) > 0)
    <x-nx-card class="mb-3">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-[12px] font-medium text-[color:var(--nx-text)]">Ordnungsgrad</span>
            <span class="text-[12px] tabular-nums text-[color:var(--nx-muted)]">
                <span class="font-semibold text-[color:var(--nx-text)]">{{ $cov['pct'] }}%</span> in Cluster · {{ $cov['unclustered_pct'] }}% ohne Bezug
            </span>
        </div>
        <div class="h-1.5 rounded-full bg-[color:var(--nx-line)] overflow-hidden">
            <div class="h-full rounded-full" style="width: {{ max(2, $cov['pct']) }}%; background: {{ $barColor($cov['pct']) }}"></div>
        </div>
        <p class="text-[11px] text-[color:var(--nx-faint)] mt-1.5">{{ number_format($cov['clustered']) }} von {{ number_format($cov['total']) }} Keywords einem Cluster zugeordnet · {{ number_format($cov['ranking']) }} ranken bereits.</p>
    </x-nx-card>
@endif

@if($cls->isNotEmpty())
    <h3 class="text-[13px] font-semibold text-[color:var(--nx-text)] mb-1">Durchdringung je Cluster</h3>
    <p class="text-[11px] text-[color:var(--nx-faint)] mb-3">IST (ranken) von SOLL (Ziel laut Cluster). Höher = Thema tiefer besetzt.</p>
    <x-nx-card flush>
        <x-nx-table>
            <x-nx-table-header>
                <x-nx-table-header-cell>Cluster</x-nx-table-header-cell>
                <x-nx-table-header-cell align="right">SOLL</x-nx-table-header-cell>
                <x-nx-table-header-cell align="right">IST</x-nx-table-header-cell>
                <x-nx-table-header-cell>Durchdringung</x-nx-table-header-cell>
                <x-nx-table-header-cell align="right">Volumen</x-nx-table-header-cell>
            </x-nx-table-header>
            <x-nx-table-body>
                @foreach($cls as $c)
                    <x-nx-table-row>
                        <x-nx-table-cell><span class="text-[color:var(--nx-text)]">{{ $c['name'] }}</span></x-nx-table-cell>
                        <x-nx-table-cell align="right"><span class="tabular-nums text-[color:var(--nx-muted)]">{{ $c['soll'] }}</span></x-nx-table-cell>
                        <x-nx-table-cell align="right"><span class="tabular-nums font-medium text-[color:var(--nx-text)]">{{ $c['ist'] }}</span></x-nx-table-cell>
                        <x-nx-table-cell>
                            <div class="flex items-center gap-2" style="min-width:140px">
                                <div class="flex-1 h-1.5 rounded-full bg-[color:var(--nx-line)] overflow-hidden">
                                    <div class="h-full rounded-full" style="width: {{ max(2, $c['pct']) }}%; background: {{ $barColor($c['pct']) }}"></div>
                                </div>
                                <span class="text-[11px] tabular-nums text-[color:var(--nx-muted)] w-9 text-right">{{ $c['pct'] }}%</span>
                            </div>
                        </x-nx-table-cell>
                        <x-nx-table-cell align="right"><span class="tabular-nums text-[color:var(--nx-muted)]">{{ number_format($c['volume']) }}</span></x-nx-table-cell>
                    </x-nx-table-row>
                @endforeach
            </x-nx-table-body>
        </x-nx-table>
    </x-nx-card>
@endif
