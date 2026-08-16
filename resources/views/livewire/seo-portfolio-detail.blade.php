<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$portfolio->name" icon="heroicon-o-rocket-launch" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wirkungsräume', 'route' => 'seo.portfolios'],
            ['label' => $portfolio->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-5xl">
            {{-- Kopf --}}
            <div class="flex items-start justify-between gap-4 mb-4">
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-gray-900">{{ $portfolio->name }}</h1>
                    @if($portfolio->goal)
                        <p class="text-[13px] text-gray-500 mt-0.5">🎯 {{ $portfolio->goal }}</p>
                    @endif
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    @if($health['can_distribute'])
                        <button wire:click="analyze" wire:target="analyze" wire:loading.attr="disabled"
                                class="text-[13px] font-medium px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                            <span wire:loading.remove wire:target="analyze">🤖 Verteilung vorschlagen</span>
                            <span wire:loading wire:target="analyze">Analysiere…</span>
                        </button>
                    @else
                        <span class="text-[13px] font-medium px-3 py-1.5 rounded-md border border-gray-200 text-gray-300 cursor-not-allowed inline-flex items-center gap-1.5"
                              title="{{ $health['block_reason'] }}">
                            🔒 Verteilung vorschlagen
                        </span>
                    @endif
                    <button wire:click="openAddUrls" class="text-[13px] font-medium px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">
                        + URLs hinzufügen
                    </button>
                </div>
            </div>

            {{-- KI-Verteilungs-Vorschlag --}}
            @if($advice)
                <div class="mb-6 bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <span class="text-[12px] font-semibold text-gray-700 inline-flex items-center gap-1.5">🤖 KI-Verteilung</span>
                        <button wire:click="$set('advice', null)" class="text-gray-400 hover:text-gray-700 text-[11px]">schließen</button>
                    </div>
                    <div class="p-4">
                        @if(!empty($advice['error']))
                            <p class="text-[13px] text-rose-600">{{ $advice['error'] }}</p>
                        @else
                            <style>
                                .wr-advice{font-size:13px;color:#374151;line-height:1.6}
                                .wr-advice h1,.wr-advice h2,.wr-advice h3{font-weight:600;color:#111827;font-size:13px;margin:.75rem 0 .25rem}
                                .wr-advice strong{color:#111827}
                                .wr-advice ul{list-style:disc;padding-left:1.25rem;margin:.4rem 0}
                                .wr-advice ol{list-style:decimal;padding-left:1.25rem;margin:.4rem 0}
                                .wr-advice li{margin:.15rem 0}
                                .wr-advice p{margin:.4rem 0}
                            </style>
                            <div class="wr-advice">{!! \Illuminate\Support\Str::markdown($advice['text']) !!}</div>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Aggregat-KPIs (Property-Ebene: Mitglieder inkl. eigener Unterseiten, dedupliziert) --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-1">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-1">Sichtbarkeit</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($agg['visibility'], 0) }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-1">Keywords</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($agg['keywords']) }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-1">Suchvolumen</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($agg['search_volume']) }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-1">URLs</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($agg['urls']) }}</div>
                </div>
            </div>
            <p class="text-[11px] text-gray-400 mb-4">Zahlen auf Property-Ebene — jede Mitglieds-URL inkl. ihrer eigenen Unterseiten, über den Verbund dedupliziert (deckungsgleich mit der URL-Detailseite).</p>

            {{-- Datenquellen-Abdeckung — wie viele Sites je Quelle Daten haben --}}
            <div class="mb-6">
                @include('seo::partials.data-source-coverage', ['urls' => $members])
            </div>

            {{-- Reifegrad — der Optimierungs-Trichter (Phase = erstes Gate, das reißt) --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                <div class="flex items-baseline justify-between mb-3">
                    <h2 class="text-[13px] font-semibold text-gray-700">Reifegrad</h2>
                    <span class="text-[11px] text-gray-400">Optimierungs-Trichter — ein Schritt nach dem anderen.</span>
                </div>

                {{-- Stepper --}}
                <div class="flex items-center gap-1 mb-3 flex-wrap">
                    @foreach($health['phases'] as $i => $ph)
                        @php($c = $ph['status'] === 'done' ? '#15803d' : ($ph['status'] === 'current' ? '#0f766e' : '#d1d5db'))
                        @if($i > 0)
                            <div class="h-px w-4" style="background:{{ $ph['status'] === 'locked' ? '#e5e7eb' : '#99c9c2' }}"></div>
                        @endif
                        <span class="inline-flex items-center gap-1 text-[12px] px-2 py-1 rounded-md"
                              style="color:{{ $c }};{{ $ph['status'] === 'current' ? 'background:#f0fdfa;font-weight:600;' : '' }}">
                            {{ $ph['status'] === 'done' ? '✓' : ($ph['status'] === 'current' ? '●' : '○') }}
                            {{ $ph['label'] }}
                        </span>
                    @endforeach
                </div>

                {{-- Aktueller Zug --}}
                <div class="rounded-md px-3 py-2.5 mb-3" style="background:#f0fdfa">
                    <div class="text-[12px] text-gray-700">
                        <span class="font-semibold" style="color:#0f766e">Du bist in „{{ $health['current_label'] }}"</span>
                        — nächster Zug: <span class="font-medium">{{ $health['next_action'] }}</span>
                    </div>
                    <div class="text-[11px] text-gray-500 mt-0.5">{{ $health['reason'] }}</div>
                </div>

                {{-- Dimensionen (was wir heute messen) --}}
                <div class="grid grid-cols-2 gap-4">
                    @foreach(['ordnung' => 'Ordnung', 'durchdringung' => 'Durchdringung'] as $key => $label)
                        @php($v = $health['dimensions'][$key] ?? 0)
                        <div>
                            <div class="flex items-center justify-between text-[11px] mb-1">
                                <span class="text-gray-500">{{ $label }}</span>
                                <span class="tabular-nums text-gray-700 font-medium">{{ $v }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $v >= 70 ? 'bg-green-500' : ($v >= 30 ? 'bg-amber-500' : 'bg-gray-300') }}" style="width: {{ max(2, $v) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($health['wirkung']['has_data'] ?? false)
                    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center justify-between gap-3 flex-wrap text-[12px]">
                        <span class="text-gray-500">Wirkung (Conversions)</span>
                        <span class="text-gray-700"><span class="font-semibold tabular-nums">{{ number_format($health['wirkung']['conversions']) }}</span> gesamt <span class="text-gray-400">· davon</span> <span class="font-medium tabular-nums" style="color:#15803d">{{ number_format($health['wirkung']['organic_conversions'] ?? 0) }} organisch</span> <span class="text-gray-400">(30T)</span></span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Weitere Dimensionen (Suchperformance/GSC · Seiten-Qualität) folgen mit den Daten.</p>
                @else
                    <p class="text-[11px] text-gray-400 mt-3">Weitere Dimensionen (Suchperformance · Seiten-Qualität · Wirkung) folgen, sobald GSC/On-Page/Plausible-Daten erhoben sind.</p>
                @endif
            </div>

            {{-- Verbund-Entwicklung über Zeit — der Nordstern: steigt die gemeinsame Sichtbarkeit? --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                <div class="flex items-baseline justify-between mb-2">
                    <div>
                        <h2 class="text-[13px] font-semibold text-gray-700">Verbund-Entwicklung</h2>
                        <p class="text-[11px] text-gray-400">Gemeinsame Sichtbarkeit über Zeit — der Nordstern.</p>
                    </div>
                    @if($trend['count'] >= 2 && $trend['delta'] !== null)
                        @php($up = $trend['delta'] >= 0)
                        <span class="text-[12px] font-semibold tabular-nums" style="color:{{ $up ? '#15803d' : '#be123c' }}">
                            {{ $up ? '▲' : '▼' }} {{ number_format(abs($trend['delta']), 0) }}
                            <span class="font-normal text-gray-400">seit Start</span>
                        </span>
                    @endif
                </div>

                @if($trend['count'] === 0)
                    <div class="rounded-lg border border-dashed border-gray-200 px-4 py-6 text-center">
                        <p class="text-[12px] text-gray-500">Noch keine Messpunkte.</p>
                        <p class="text-[11px] text-gray-400 mt-0.5">Die Verbund-Entwicklung erscheint, sobald der erste Snapshot gelaufen ist — im Takt, in dem neue Daten eingesammelt werden.</p>
                    </div>
                @elseif($trend['count'] === 1)
                    <div class="rounded-lg border border-dashed border-gray-200 px-4 py-4 flex items-center justify-between gap-4">
                        <div>
                            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($trend['current'], 0) }}</div>
                            <div class="text-[11px] text-gray-400">1 Messpunkt seit {{ \Illuminate\Support\Carbon::parse($trend['since'])->format('d.m.Y') }}</div>
                        </div>
                        <p class="text-[11px] text-gray-400 max-w-[220px] text-right">Die Linie entsteht ab dem zweiten Messpunkt — der Rahmen steht schon.</p>
                    </div>
                @else
                    @include('seo::partials.sparkline', ['data' => array_column($trend['points'], 'visibility'), 'color' => '#0f766e', 'height' => 120, 'type' => 'area'])
                    <div class="flex items-center justify-between mt-1 text-[11px] text-gray-400">
                        <span>{{ \Illuminate\Support\Carbon::parse($trend['since'])->format('d.m.') }} → {{ \Illuminate\Support\Carbon::parse($trend['points'][count($trend['points']) - 1]['date'])->format('d.m.Y') }}</span>
                        <span>{{ $trend['count'] }} Messpunkte</span>
                    </div>
                @endif
            </div>

            {{-- Wirkung im Verbund — die Plausible-Fakten aufs Portfolio gehoben --}}
            @if($verbundWirkung['has_data'])
                <div class="mb-6">
                    <h2 class="text-[13px] font-semibold text-gray-700 mb-1">Wirkung im Verbund</h2>
                    <p class="text-[11px] text-gray-400 mb-3">Was die Properties wirklich <span class="font-medium">wandeln</span> — <span class="font-medium">Conversions gesamt</span> (der Geschäftswert, inkl. App/Direkt/Referral) und <span class="font-medium">davon organisch</span> (der reine SEO-Anteil). Große Lücke bei Endpunkt-Seiten = Chance, den Verbund dorthin zu speisen (Plausible, 30 Tage).</p>

                    {{-- Conversion-Verlauf (steigt die Wirkung?) --}}
                    @if($conversionTrend['count'] >= 2)
                        <div class="bg-white rounded-lg border border-gray-200 p-3 mb-3">
                            <div class="flex items-baseline justify-between mb-1">
                                <span class="text-[11px] text-gray-500">Conversion-Verlauf (Verbund)</span>
                                @php($up = ($conversionTrend['delta'] ?? 0) >= 0)
                                <span class="text-[12px] font-semibold tabular-nums" style="color:{{ $up ? '#15803d' : '#be123c' }}">{{ $up ? '▲' : '▼' }} {{ number_format(abs($conversionTrend['delta'])) }} seit Start</span>
                            </div>
                            @include('seo::partials.sparkline', ['data' => array_column($conversionTrend['points'], 'value'), 'color' => '#0f766e', 'height' => 60, 'type' => 'area'])
                            <div class="text-[11px] text-gray-400 mt-1">{{ \Illuminate\Support\Carbon::parse($conversionTrend['since'])->format('d.m.') }} → heute · {{ $conversionTrend['count'] }} Messpunkte</div>
                        </div>
                    @elseif($conversionTrend['count'] === 1)
                        <div class="rounded-lg border border-dashed border-gray-200 px-3 py-2 mb-3 text-[11px] text-gray-400">
                            Conversion-Verlauf: 1 Messpunkt seit {{ \Illuminate\Support\Carbon::parse($conversionTrend['since'])->format('d.m.Y') }} — die Kurve entsteht ab dem zweiten Lauf.
                        </div>
                    @endif

                    {{-- Wirkung je Property --}}
                    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto mb-3">
                        <table class="w-full text-[13px]" style="min-width:560px">
                            <thead>
                                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                    <th class="text-left px-4 py-2">Property</th>
                                    <th class="text-right px-4 py-2">Org. Besucher</th>
                                    <th class="text-right px-4 py-2">Conversions</th>
                                    <th class="text-right px-4 py-2">davon organisch</th>
                                    <th class="text-right px-4 py-2">Rate</th>
                                    <th class="text-left px-4 py-2">Top-Ziel</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($verbundWirkung['members'] as $m)
                                    <tr class="border-b border-gray-50 last:border-0">
                                        <td class="px-4 py-2 text-gray-700 font-medium">{{ $m['domain'] }}</td>
                                        <td class="px-4 py-2 text-right text-gray-500 tabular-nums">{{ $m['org_visitors'] > 0 ? number_format($m['org_visitors']) : '—' }}</td>
                                        <td class="px-4 py-2 text-right font-semibold text-gray-900 tabular-nums">{{ number_format($m['conversions']) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums" style="color:{{ ($m['organic'] ?? 0) > 0 ? '#15803d' : '#9ca3af' }}">{{ number_format($m['organic'] ?? 0) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium" style="color:{{ $m['rate'] >= 10 ? '#15803d' : ($m['rate'] >= 3 ? '#b45309' : '#6b7280') }}">{{ number_format($m['rate'], 1) }}%</td>
                                        <td class="px-4 py-2 text-gray-500 text-[12px]">{{ $m['goal'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    {{-- Top konvertierende Seiten Verbund-weit --}}
                    @if(! empty($verbundWirkung['topPages']))
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="text-[12px] font-medium text-gray-700 mb-0.5">Top konvertierende Seiten im Verbund</div>
                            <div class="text-[11px] text-gray-400 mb-2.5">Welche einzelnen Seiten über alle Properties den Wert bringen — die „mehr davon"-Liste.</div>
                            <div class="overflow-x-auto">
                                <table class="w-full text-[12px]" style="min-width:520px">
                                    <thead>
                                        <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                            <th class="text-left py-1.5 pr-3">Seite</th>
                                            <th class="text-left py-1.5 px-2">Property</th>
                                            <th class="text-right py-1.5 px-2">Conversions</th>
                                            <th class="text-right py-1.5 pl-2">beste Rate</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($verbundWirkung['topPages'] as $p)
                                            <tr class="border-b border-gray-50 last:border-0">
                                                <td class="py-1.5 pr-3 text-gray-700" style="max-width:230px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $p['page'] }}">{{ $p['page'] }}</td>
                                                <td class="py-1.5 px-2 text-gray-400">{{ $p['site'] }}</td>
                                                <td class="py-1.5 px-2 text-right text-gray-600 tabular-nums font-medium">{{ number_format($p['conversions']) }}</td>
                                                <td class="py-1.5 pl-2 text-right tabular-nums font-semibold" style="color:{{ $p['rate'] >= 20 ? '#15803d' : ($p['rate'] >= 5 ? '#b45309' : '#6b7280') }}">{{ number_format($p['rate'], 1) }}%</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Verbund-Verweise — speist der Verbund sich selbst? --}}
            @if($verbundReferrals['has_data'])
                <div class="mb-8">
                    <h2 class="text-[13px] font-semibold text-gray-700 mb-1">Verbund-Verweise</h2>
                    <p class="text-[11px] text-gray-400 mb-3 max-w-2xl">Besucher, die eine Verbund-Property per Verweis an eine andere schickt — der Ranker speist den Endpunkt. Das ist der Verbund bei der Arbeit, nicht behauptet, sondern in Plausible gemessen (30 Tage).</p>

                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="flex items-baseline gap-2 mb-3">
                            <span class="text-[22px] font-semibold text-gray-800 tabular-nums" style="color:#0f766e">{{ number_format($verbundReferrals['total']) }}</span>
                            <span class="text-[12px] text-gray-500">Besucher intern verwiesen · {{ count($verbundReferrals['edges']) }} Verweis-Wege</span>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-[12px]" style="min-width:480px">
                                <thead>
                                    <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                        <th class="text-left py-1.5 pr-3">von (Quelle)</th>
                                        <th class="text-left py-1.5 px-2">an (Property)</th>
                                        <th class="text-right py-1.5 pl-2">Besucher</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($verbundReferrals['edges'] as $e)
                                        <tr class="border-b border-gray-50 last:border-0">
                                            <td class="py-1.5 pr-3 text-gray-700">
                                                {{ $e['from'] }}
                                                @if($e['from_is_member'])
                                                    <span class="ml-1 inline-block text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-teal-100 text-teal-700 align-middle">Mitglied</span>
                                                @endif
                                            </td>
                                            <td class="py-1.5 px-2 text-gray-500">{{ $e['to'] }}</td>
                                            <td class="py-1.5 pl-2 text-right text-gray-700 tabular-nums font-semibold">{{ number_format($e['visitors']) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-3">„Mitglied" = Quelle ist selbst eine Property dieses Wirkungsraums. Ohne Badge: Verbund-Nachbar außerhalb des Portfolios.</p>
                    </div>
                </div>
            @endif

            {{-- Mitglieder --}}
            <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Mitglieder <span class="text-gray-400 font-normal">(kontrollierte URLs)</span></h2>
            <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
                <table class="w-full text-[13px]" style="min-width: 640px">
                    <thead>
                        <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                            <th class="text-left px-4 py-2">URL</th>
                            <th class="text-right px-4 py-2">Keywords</th>
                            <th class="text-right px-4 py-2">Suchvol.</th>
                            <th class="text-right px-4 py-2">Sichtbarkeit</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($members as $url)
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                @php($t = $memberTotals[$url->id] ?? ['keywords' => $url->keyword_count, 'search_volume' => $url->total_search_volume, 'visibility' => $url->visibility_score, 'subpages' => 0])
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $url->domain }}{{ $url->path !== '/' ? $url->path : '' }}</a>
                                    @if($t['subpages'] > 0)
                                        <span class="ml-1.5 text-[10px] text-gray-400" title="inkl. {{ $t['subpages'] }} Unterseite(n)">+{{ $t['subpages'] }} Unterseite{{ $t['subpages'] > 1 ? 'n' : '' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ number_format($t['keywords']) }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ number_format($t['search_volume']) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-900 tabular-nums">{{ number_format($t['visibility'], 0) }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <button wire:click="removeUrl({{ $url->id }})" wire:confirm="URL aus dem Wirkungsraum entfernen?" class="text-gray-300 hover:text-rose-500" title="Entfernen">&times;</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-[13px] text-gray-400">Noch keine URLs. Über „+ URLs hinzufügen" kontrollierte Seiten aufnehmen.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Durchdringung je Cluster (IST rankend / SOLL laut Cluster) --}}
            @if($penetration['clusters']->isNotEmpty() || $penetration['unclustered'])
                <div class="mt-8">
                    @include('seo::partials.scope-penetration', ['clusters' => $penetration['clusters'], 'coverage' => $coverage])
                    @if($penetration['unclustered'] || ($portfolio->clustering_status ?? null))
                        <div class="mt-3 bg-white rounded-lg border border-dashed border-gray-200 px-4 py-3"
                            @if(($portfolio->clustering_status ?? null) === 'running') wire:poll.10s @endif>
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <span class="text-[12px] text-gray-600">
                                    @if($penetration['unclustered'])
                                        Ungeclusterter Rest: <span class="font-medium">{{ number_format($penetration['unclustered']['soll']) }}</span> Keywords, davon <span class="font-medium">{{ number_format($penetration['unclustered']['ist']) }}</span> wild rankend
                                    @else
                                        Kein ungeclusterter Rest mehr — alles geordnet.
                                    @endif
                                </span>

                                @if(($portfolio->clustering_status ?? null) === 'running')
                                    <span class="text-[12px] font-medium" style="color:#1d4ed8">⏳ Nach-Clustern läuft…</span>
                                @elseif($clusterable >= 2)
                                    <div class="flex items-center gap-2">
                                        <label class="text-[11px] text-gray-500">ab Vol.
                                            <select wire:model.live="clusterMinVolume" class="ml-1 text-[11px] border border-gray-200 rounded px-1 py-0.5 bg-white">
                                                <option value="0">alle</option>
                                                <option value="10">10</option>
                                                <option value="50">50</option>
                                                <option value="100">100</option>
                                            </select>
                                        </label>
                                        <button wire:click="clusterRest" wire:loading.attr="disabled"
                                            class="text-[12px] font-medium text-white rounded px-3 py-1.5" style="background:#0f766e">
                                            Rest clustern
                                            <span style="opacity:.8">({{ number_format($clusterable) }} KW · ~{{ number_format($clusterCostCents/100, 2, ',', '.') }} €)</span>
                                        </button>
                                    </div>
                                @else
                                    <span class="text-[11px] text-gray-400">→ nichts über der Schwelle zu clustern</span>
                                @endif
                            </div>

                            <p class="text-[11px] text-gray-400 mt-1.5">Bündelt nur den <span class="font-medium">ungeclusterten</span> Rest zu neuen Themen (SERP-Overlap) — bereits zugeordnete Keywords bleiben unberührt.</p>

                            @if($clusterFlash)
                                <p class="text-[11px] mt-1.5" style="color:#0f766e">{{ $clusterFlash }}</p>
                            @endif

                            @php($cr = is_array($portfolio->clustering_result ?? null) ? $portfolio->clustering_result : null)
                            @if(($portfolio->clustering_status ?? null) === 'completed' && $cr && empty($cr['error']))
                                <p class="text-[11px] mt-1.5" style="color:#15803d">✓ {{ (int) ($cr['clusters_created'] ?? 0) }} neue Cluster · {{ (int) ($cr['keywords_clustered'] ?? 0) }} Keywords geordnet · {{ (int) ($cr['singletons_remaining'] ?? 0) }} Einzelgänger übrig</p>
                            @elseif(($portfolio->clustering_status ?? null) === 'failed' || ($cr && ! empty($cr['error'])))
                                <p class="text-[11px] mt-1.5" style="color:#b91c1c">Nach-Clustern fehlgeschlagen{{ $cr && ! empty($cr['error']) ? ': ' . $cr['error'] : '' }}.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{-- Semantische Karte — die Wirkungsraum-Linse auf die Keyword-Bedeutungen (Slice 2) --}}
            <div class="mb-8" {{ (($semantic['status'] ?? null) === 'running' || ($portfolio->clustering_status ?? null) === 'running') ? 'wire:poll.5s' : '' }}>
                <div class="flex items-start justify-between gap-3 mb-1">
                    <h2 class="text-[13px] font-semibold text-gray-700">Semantische Karte</h2>
                    <div class="flex items-center gap-1 shrink-0">
                        <button wire:click="buildSemanticMap('own')" wire:loading.attr="disabled" @disabled(($semantic['status'] ?? null) === 'running')
                                class="text-[11px] px-2.5 py-1 rounded disabled:opacity-50 {{ $semanticSource === 'own' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-600' }}" title="Faden 1 — ordnen, was wir schon haben">eigene</button>
                        <button wire:click="buildSemanticMap('both')" wire:loading.attr="disabled" @disabled(($semantic['status'] ?? null) === 'running')
                                class="text-[11px] px-2.5 py-1 rounded disabled:opacity-50 {{ $semanticSource === 'both' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-600' }}" title="Faden 2 — erobern: + Wettbewerber-Keywords (das Grau)">+ Wettbewerber</button>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 mb-2 max-w-2xl">Die Keywords nach <span class="font-medium">Bedeutung</span> geordnet — Vorschlag, kein SERP. <span class="font-medium">eigene</span> = ordnen was wir haben · <span class="font-medium">+ Wettbewerber</span> = das <span style="color:#e11d48">Grau</span> (wozu ranken die, das uns fehlt). Ein Zimmer <span class="font-medium">„übernehmen"</span> lässt SERP prüfen und macht einen echten Cluster (umkehrbar). Nichts gespeichert, bis du übernimmst.</p>
                @if($clusterFlash)
                    <p class="text-[11px] mb-3" style="color:#0f766e">{{ $clusterFlash }}</p>
                @endif

                @php($sm = $semantic['map'] ?? null)
                @php($smStatus = $semantic['status'] ?? null)

                @if($smStatus === 'running')
                    <div class="bg-white rounded-lg border border-gray-200 p-4 text-[12px] text-gray-500">Karte wird gebaut … (Nachbarschaftssuche über Qdrant) — aktualisiert sich automatisch.</div>
                @elseif($smStatus === 'failed')
                    <div class="bg-white rounded-lg border border-gray-200 p-4 text-[12px]" style="color:#b91c1c">Aufbau fehlgeschlagen{{ ! empty($sm['error']) ? ': ' . $sm['error'] : '' }}. Läuft <code>seo:embed-keywords</code> schon?</div>
                @elseif($sm && empty($sm['error']))
                    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1 mb-3 text-[12px] text-gray-600">
                        <span><span class="font-semibold tabular-nums">{{ number_format($sm['stats']['total']) }}</span> Keywords</span>
                        <span><span class="font-semibold tabular-nums" style="color:#0f766e">{{ number_format($sm['stats']['neighborhoods']) }}</span> Nachbarschaften ({{ number_format($sm['stats']['grouped']) }} gruppiert)</span>
                        <span><span class="font-semibold tabular-nums" style="color:#b45309">{{ number_format($sm['stats']['outliers']) }}</span> Ausreißer</span>
                        @if(($sm['source'] ?? 'own') === 'both')
                            <span><span class="font-semibold tabular-nums" style="color:#e11d48">{{ number_format($sm['stats']['competitors'] ?? 0) }}</span> Wettbewerber-KW</span>
                            <span><span class="font-semibold tabular-nums" style="color:#e11d48">{{ number_format($sm['stats']['opportunities'] ?? 0) }}</span> Chancen</span>
                        @endif
                        @if(! empty($sm['built_at']))<span class="text-gray-400">· {{ \Illuminate\Support\Carbon::parse($sm['built_at'])->diffForHumans() }}</span>@endif
                        @if(! empty($sm['truncated']))<span class="text-gray-400">· auf {{ number_format($sm['cap']) }} volumenstärkste begrenzt</span>@endif
                    </div>

                    @if(! empty($sm['neighborhoods']))
                        {{-- Quartiere (große Nachbarschaften, in Zimmer aufgelöst — Simulation, read-only) --}}
                        @foreach($sm['neighborhoods'] as $nbIdx => $nb)
                            @if(! empty($nb['rooms']))
                                <div class="bg-white rounded-lg border border-gray-200 p-3 mb-2">
                                    <div class="flex items-baseline justify-between gap-2 mb-2">
                                        <span class="text-[12px] font-semibold text-gray-700">
                                            <span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-teal-100 text-teal-700 align-middle mr-1">Quartier</span>{{ $nb['label'] }}@if(! empty($nb['is_opportunity']))<span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-rose-100 text-rose-700 align-middle ml-1">Chance</span>@endif
                                        </span>
                                        <span class="text-[10px] text-gray-400 shrink-0 tabular-nums">{{ $nb['size'] }} KW · {{ count($nb['rooms']) }} Zimmer · Pot ~{{ number_format($nb['potenzial'] ?? 0) }} / IST ~{{ number_format($nb['ist'] ?? 0) }}</span>
                                    </div>
                                    <div class="grid gap-2" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
                                        @foreach($nb['rooms'] as $roomIdx => $room)
                                            <div class="rounded-md border border-gray-100 p-2" style="background:#fafafa">
                                                <div class="flex items-center justify-between gap-2 mb-1">
                                                    <span class="text-[11px] font-medium {{ $room['is_rest'] ? 'text-gray-400' : 'text-gray-700' }}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $room['label'] }}@if(! empty($room['is_opportunity']))<span class="text-[8px] uppercase tracking-wide px-1 rounded bg-rose-100 text-rose-700 align-middle ml-1">Chance</span>@endif</span>
                                                    <span class="flex items-center gap-1.5 shrink-0">
                                                        <span class="text-[9px] text-gray-400 tabular-nums" title="Größe · Chance = erreichbarer Mehr-Traffic/Monat">{{ $room['size'] }} · <span style="color:#e11d48">↑{{ number_format($room['gap'] ?? 0) }}</span></span>
                                                        <button wire:click="adoptRoom({{ $nbIdx }}, {{ $roomIdx }})" wire:loading.attr="disabled" @disabled(($portfolio->clustering_status ?? null) === 'running')
                                                                class="text-[9px] px-1.5 py-0.5 rounded bg-gray-900 text-white disabled:opacity-40" title="SERP prüfen & als Cluster übernehmen">übernehmen</button>
                                                    </span>
                                                </div>
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach(array_slice($room['keywords'], 0, 6) as $kw)
                                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-white border border-gray-100 text-gray-600" title="Vol {{ number_format($kw['volume']) }}">@if(($kw['origin'] ?? 'own') === 'competitor')<span style="color:#e11d48">◆</span> @elseif(! $kw['clustered'])<span style="color:#0f766e">•</span> @endif{{ $kw['keyword'] }}</span>
                                                    @endforeach
                                                    @if($room['size'] > 6)<span class="text-[10px] text-gray-400 px-1 py-0.5">+{{ $room['size'] - 6 }}</span>@endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        {{-- Einfache Nachbarschaften (schon je ein Thema) --}}
                        <div class="grid gap-2 mb-2" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
                            @foreach($sm['neighborhoods'] as $nbIdx => $nb)
                                @if(empty($nb['rooms']))
                                    <div class="bg-white rounded-lg border border-gray-200 p-3">
                                        <div class="flex items-center justify-between gap-2 mb-1.5">
                                            <span class="text-[12px] font-medium text-gray-700" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $nb['label'] }}@if(! empty($nb['is_opportunity']))<span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-rose-100 text-rose-700 align-middle ml-1">Chance</span>@endif</span>
                                            <span class="flex items-center gap-1.5 shrink-0">
                                                <span class="text-[10px] text-gray-400 tabular-nums" title="Keywords · Potenzial ~Besuche/Mon bei Top-Rang / IST ~aktuell erreicht">{{ $nb['size'] }} KW · Pot ~{{ number_format($nb['potenzial'] ?? 0) }} / IST ~{{ number_format($nb['ist'] ?? 0) }}</span>
                                                <button wire:click="adoptSimple({{ $nbIdx }})" wire:loading.attr="disabled" @disabled(($portfolio->clustering_status ?? null) === 'running')
                                                        class="text-[9px] px-1.5 py-0.5 rounded bg-gray-900 text-white disabled:opacity-40" title="SERP prüfen & als Cluster übernehmen">übernehmen</button>
                                            </span>
                                        </div>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach(array_slice($nb['keywords'], 0, 8) as $kw)
                                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600" title="Vol {{ number_format($kw['volume']) }}">@if(($kw['origin'] ?? 'own') === 'competitor')<span style="color:#e11d48">◆</span> @elseif(! $kw['clustered'])<span style="color:#0f766e">•</span> @endif{{ $kw['keyword'] }}</span>
                                            @endforeach
                                            @if($nb['size'] > 8)<span class="text-[10px] text-gray-400 px-1 py-0.5">+{{ $nb['size'] - 8 }}</span>@endif
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <p class="text-[10px] text-gray-400 mb-4"><span class="text-teal-700 font-medium">Quartier</span> = großes Feld in Zimmer aufgelöst · <span class="text-rose-700 font-medium">Chance</span> = überwiegend Wettbewerber-Keywords (Grau) · <span style="color:#0f766e">•</span> ungeclustert (eigen) · <span style="color:#e11d48">◆</span> Wettbewerber-Keyword · <span style="color:#e11d48">↑</span> Chance = erreichbarer Mehr-Traffic/Mon (Pot − IST) · sortiert nach größter Chance</p>
                    @endif

                    <div class="grid gap-3" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
                        @if(! empty($sm['outliers']))
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[12px] font-medium text-gray-700 mb-0.5">Ausreißer</div>
                                <div class="text-[10px] text-gray-400 mb-2">Keine semantischen Nachbarn im Wirkungsraum — Quarantäne-Kandidaten.</div>
                                <div class="flex flex-wrap gap-1">
                                    @foreach($sm['outliers'] as $kw)
                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500" title="Vol {{ number_format($kw['volume']) }}">{{ $kw['keyword'] }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if(! empty($sm['themefar']))
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[12px] font-medium text-gray-700 mb-0.5">Themenfern</div>
                                <div class="text-[10px] text-gray-400 mb-2">Geringste Nähe zur Wirkungsraum-Identität — zum Aussortieren prüfen.</div>
                                <div class="flex flex-col gap-0.5">
                                    @foreach(array_slice($sm['themefar'], 0, 20) as $kw)
                                        <div class="flex items-baseline justify-between gap-2 text-[11px]">
                                            <span class="text-gray-600" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $kw['keyword'] }}</span>
                                            <span class="text-gray-400 tabular-nums shrink-0">{{ $kw['anchor_score'] !== null ? number_format($kw['anchor_score'], 2) : '—' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    @if(! empty($sm['anchor']))
                        <p class="text-[10px] text-gray-400 mt-2">Anker: {{ \Illuminate\Support\Str::limit($sm['anchor'], 140) }}</p>
                    @endif
                @else
                    <div class="bg-white rounded-lg border border-gray-200 p-4 text-[12px] text-gray-500">Noch keine Karte. Der Knopf liest die Keyword-Vektoren aus Qdrant und zeigt Nachbarschaften, Ausreißer und themenferne Keywords.</div>
                @endif
            </div>

            {{-- Wettbewerber-Benchmark (der Markt um den Verbund) --}}
            @if($competitors->isNotEmpty())
                <div class="mt-8">
                    <div class="flex items-baseline justify-between mb-3">
                        <div>
                            <h2 class="text-[13px] font-semibold text-gray-700">Wettbewerber-Benchmark</h2>
                            <p class="text-[11px] text-gray-400">Der Markt um den Verbund — wer rankt für dieselben Themen.</p>
                        </div>
                        <div class="text-right">
                            <div class="text-[13px] font-semibold text-gray-900 tabular-nums">{{ number_format($agg['visibility'], 0) }}</div>
                            <div class="text-[10px] uppercase tracking-wide text-gray-400">Verbund (wir)</div>
                        </div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
                        <table class="w-full text-[13px]" style="min-width: 480px">
                            <thead>
                                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                    <th class="text-left px-4 py-2">Wettbewerber-Domain</th>
                                    <th class="text-right px-4 py-2">gemeinsame KWs</th>
                                    <th class="text-right px-4 py-2">Sichtbarkeit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($competitors as $c)
                                    <tr class="border-b border-gray-50 last:border-0">
                                        <td class="px-4 py-2.5">
                                            <span class="inline-flex items-center gap-2">
                                                <span class="w-2 h-2 rounded-full bg-amber-400 shrink-0"></span>
                                                <span class="text-gray-700">{{ $c->domain }}</span>
                                            </span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ number_format($c->shared_keywords) }}</td>
                                        <td class="px-4 py-2.5 text-right tabular-nums {{ $c->visibility > $agg['visibility'] ? 'font-semibold text-rose-600' : 'text-gray-700' }}" @if($c->visibility > $agg['visibility']) title="Überholt den Verbund" @endif>{{ number_format($c->visibility, 0) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <p class="mt-6 text-[11px] text-gray-400">Nächste Ausbaustufen: KI-Vorschläge in Aktionen (Cluster-Owner, Briefs) · Snapshots im Takt der Datensammlung.</p>
        </div>

        {{-- Add-URLs-Modal (nur eigene, kontrollierte URLs) --}}
        @if($showAddUrls)
            <div class="fixed inset-0 z-50 flex items-start justify-center bg-black/30 p-4 pt-20" wire:click.self="$set('showAddUrls', false)">
                <div class="bg-white rounded-lg border border-gray-200 shadow-lg w-full max-w-lg">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <span class="text-[13px] font-semibold text-gray-800">Kontrollierte URLs hinzufügen</span>
                        <button wire:click="$set('showAddUrls', false)" class="text-gray-400 hover:text-gray-700">&times;</button>
                    </div>
                    <div class="p-4">
                        <input type="text" wire:model.live.debounce.300ms="urlSearch" placeholder="URL/Domain suchen…"
                               class="w-full text-[13px] border border-gray-300 rounded-md px-3 py-2 mb-3" />
                        <div class="max-h-72 overflow-y-auto divide-y divide-gray-50">
                            @forelse($availableUrls as $url)
                                <label class="flex items-center gap-2 px-1 py-2 cursor-pointer">
                                    <input type="checkbox" wire:model="selectedUrlIds" value="{{ $url->id }}" class="rounded border-gray-300">
                                    <span class="text-[12px] text-gray-700">{{ $url->domain }}{{ $url->path !== '/' ? $url->path : '' }}</span>
                                </label>
                            @empty
                                <div class="px-1 py-6 text-center text-[12px] text-gray-400">Keine passenden eigenen URLs gefunden.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-end gap-2">
                        <button wire:click="$set('showAddUrls', false)" class="text-[13px] text-gray-500 hover:text-gray-800">Abbrechen</button>
                        <button wire:click="addUrls" class="text-[13px] font-medium px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">Hinzufügen</button>
                    </div>
                </div>
            </div>
        @endif
    </x-ui-page-container>
</x-ui-page>
