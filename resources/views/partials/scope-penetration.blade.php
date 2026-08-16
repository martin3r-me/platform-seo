@props(['clusters' => null, 'coverage' => null])

{{--
    Gemeinsames Scope-Panel — Ordnungsgrad + Durchdringung je Cluster.
    Reine Lesart einer URL-Menge (URL+Unterseiten / Portfolio / Liste), damit
    sich der Nutzer an EINE Sprache gewöhnt. Daten: Platform\Seo\Services\
    SeoScopeMetrics. Aktionen (clustern etc.) bleiben scope-spezifisch außerhalb.
--}}
@php
    $cov = $coverage ?? ['pct' => 0, 'unclustered_pct' => 0, 'total' => 0, 'clustered' => 0, 'ranking' => 0];
    $cls = $clusters ?? collect();
@endphp

@if(($cov['total'] ?? 0) > 0)
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3 mb-3">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-[12px] font-medium text-gray-700">Ordnungsgrad</span>
            <span class="text-[12px] tabular-nums text-gray-500">
                <span class="font-semibold text-gray-800">{{ $cov['pct'] }}%</span> in Cluster · {{ $cov['unclustered_pct'] }}% ohne Bezug
            </span>
        </div>
        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
            <div class="h-full rounded-full {{ $cov['pct'] >= 70 ? 'bg-green-500' : ($cov['pct'] >= 30 ? 'bg-amber-500' : 'bg-gray-300') }}" style="width: {{ max(2, $cov['pct']) }}%"></div>
        </div>
        <p class="text-[11px] text-gray-400 mt-1.5">{{ number_format($cov['clustered']) }} von {{ number_format($cov['total']) }} Keywords einem Cluster zugeordnet · {{ number_format($cov['ranking']) }} ranken bereits.</p>
    </div>
@endif

@if($cls->isNotEmpty())
    <h3 class="text-[13px] font-semibold text-gray-700 mb-1">Durchdringung je Cluster</h3>
    <p class="text-[11px] text-gray-400 mb-3">IST (ranken) von SOLL (Ziel laut Cluster). Höher = Thema tiefer besetzt.</p>
    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-[13px]" style="min-width: 640px">
            <thead>
                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                    <th class="text-left px-4 py-2">Cluster</th>
                    <th class="text-right px-4 py-2">SOLL</th>
                    <th class="text-right px-4 py-2">IST</th>
                    <th class="text-left px-4 py-2" style="width: 160px">Durchdringung</th>
                    <th class="text-right px-4 py-2">Volumen</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cls as $c)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="px-4 py-2.5 text-gray-700">{{ $c['name'] }}</td>
                        <td class="px-4 py-2.5 text-right text-gray-500 tabular-nums">{{ $c['soll'] }}</td>
                        <td class="px-4 py-2.5 text-right font-medium text-gray-800 tabular-nums">{{ $c['ist'] }}</td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full rounded-full {{ $c['pct'] >= 70 ? 'bg-green-500' : ($c['pct'] >= 30 ? 'bg-amber-500' : 'bg-gray-300') }}" style="width: {{ max(2, $c['pct']) }}%"></div>
                                </div>
                                <span class="text-[11px] tabular-nums text-gray-500 w-9 text-right">{{ $c['pct'] }}%</span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5 text-right text-gray-500 tabular-nums">{{ number_format($c['volume']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
