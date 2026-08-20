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

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    {{-- Innere View-Navigation des Wirkungsraums (fraktale Sidebar): Überblick ·
         die 5 Stationen (Reifegrad-Gates) · Bestand. Steuert $view. --}}
    <x-ui-page-sidebar title="Wirkungsraum" icon="heroicon-o-rocket-launch" width="w-64" storeKey="sidebarOpen">
        <div class="p-3 space-y-5">
            {{-- Überblick --}}
            <div>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5 px-2">Überblick</h3>
                <button wire:click="setView('dashboard')"
                        class="w-full text-left px-2 py-1.5 rounded text-[13px] transition-colors {{ $view === 'dashboard' ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                    Dashboard
                </button>
            </div>

            {{-- Stationen — die 5 Arbeitsschritte am Wirkungsraum (Reifegrad-Trichter) --}}
            <div>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-0.5 px-2">Stationen <span class="text-gray-300 normal-case font-normal">· der rote Faden</span></h3>
                <p class="px-2 mb-1.5 text-[9px] text-gray-400 leading-tight">Daten → Ordnen → Verteilen → Handeln → Wirkung</p>
                @foreach(['messen' => 'Daten', 'ordnen' => 'Ordnen', 'verteilen' => 'Verteilen', 'vertiefen' => 'Maßnahmen', 'konvertieren' => 'Wirkung'] as $stKey => $stLabel)
                    <button wire:click="setView('{{ $stKey }}')"
                            class="w-full flex items-center gap-2 px-2 py-1.5 rounded text-[13px] transition-colors {{ $view === $stKey ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                        <span class="text-[10px] tabular-nums text-gray-400 w-3">{{ $loop->iteration }}</span>
                        <span class="flex-1 text-left">{{ $stLabel }}</span>
                        @if(($health['current'] ?? null) === $stKey)
                            <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:#0f766e" title="Aktuelles Gate"></span>
                        @endif
                    </button>
                @endforeach
            </div>

            {{-- Bestand — was im Wirkungsraum liegt --}}
            <div>
                <h3 class="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mb-1.5 px-2">Bestand</h3>
                @foreach(['entities' => 'Entitäten', 'keywords' => 'Keywords', 'clusters' => 'Cluster', 'competitors' => 'Wettbewerber'] as $bKey => $bLabel)
                    <button wire:click="setView('{{ $bKey }}')"
                            class="w-full text-left px-2 py-1.5 rounded text-[13px] transition-colors {{ $view === $bKey ? 'bg-gray-100 font-medium text-gray-900' : 'text-gray-600 hover:bg-gray-50' }}">
                        {{ $bLabel }}
                    </button>
                @endforeach
            </div>
        </div>
    </x-ui-page-sidebar>

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
                    {{-- „Verteilung vorschlagen" ist eine Verteilen-Aktion — nur dort, nicht überall. --}}
                    @if($view === 'verteilen')
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
                    @endif
                    <button wire:click="openAddUrls" class="text-[13px] font-medium px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">
                        + URLs hinzufügen
                    </button>
                </div>
            </div>

            {{-- Dashboard — eigene, gecraftete nx-Überblickssicht (Held-Metrik +
                 Wirkungsgrad + nächster Zug + kompakter Reifegrad + Sparkline). --}}
            @if($view === 'dashboard')
                @include('seo::partials.wirkungsraum-dashboard', ['agg' => $agg, 'health' => $health, 'trend' => $trend, 'penetration' => $penetration, 'competitors' => $competitors, 'measureInbox' => $measureInbox])
            @endif

            {{-- Stationen — die Werkbank (Kontext + fokussiertes Phasen-Werkzeug).
                 Bestand-Views (Keywords/Cluster/Wettbewerber) zeigen ihre eigene
                 Liste weiter unten; das Dashboard seine gecraftete Sicht oben. --}}
            @if($station)

            {{-- Defensive Hilfe: was ist ein Wirkungsraum + wie funktioniert die gated Werkbank (wegklickbar) --}}
            @include('seo::partials.help-banner', ['lens' => 'wirkungsraum'])

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

            {{-- Auf der Daten-Station steht nur die Daten-Matrix (unten). KPIs/Abdeckung/Reifegrad/
                 Verbund gehören auf ihre jeweiligen Stationen, nicht hierher. --}}
            @if($view !== 'messen')
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
            @endif {{-- /$view !== 'messen' (KPIs + Abdeckung) --}}

            {{-- Daten-Station: Matrix URL × Quelle (Aktivierung + Kosten) --}}
            @if($view === 'messen')
                <div class="mb-8">
                    @include('seo::partials.wirkungsraum-daten', ['members' => $members, 'availableProfiles' => $availableProfiles, 'openDataUrlId' => $openDataUrlId, 'dataGscProperty' => $dataGscProperty, 'dataPlausibleSiteId' => $dataPlausibleSiteId])
                </div>
            @endif

            {{-- Maßnahmen-Station: der Posteingang — die Zentrale des Wirkungsraums --}}
            @if($view === 'vertiefen')
                <div class="mb-8">
                    @include('seo::partials.wirkungsraum-posteingang', ['measures' => $measures, 'measureFlash' => $measureFlash])
                </div>
            @endif

            {{-- Ab hier folgt der stationsübergreifende Dashboard-Block (Reifegrad, Verbund,
                 Wirkung, Rollen, Board …). Auf der Daten-Station ausgeblendet. --}}
            @if($view !== 'messen')

            {{-- Reifegrad — der Optimierungs-Trichter (Phase = erstes Gate, das reißt) --}}
            <div class="bg-white rounded-lg border border-gray-200 p-4 mb-6">
                <div class="flex items-baseline justify-between mb-3">
                    <h2 class="text-[13px] font-semibold text-gray-700">Reifegrad</h2>
                    <span class="text-[11px] text-gray-400">Optimierungs-Trichter — klick eine Phase für ihr Werkzeug.</span>
                </div>

                {{-- Stepper — klickbar: steuert, welches Phasen-Werkzeug unten gezeigt wird --}}
                <div class="flex items-center gap-1 mb-3 flex-wrap">
                    @foreach($health['phases'] as $i => $ph)
                        @php($c = $ph['status'] === 'done' ? '#15803d' : ($ph['status'] === 'current' ? '#0f766e' : '#9ca3af'))
                        @if($i > 0)
                            <div class="h-px w-4" style="background:{{ $ph['status'] === 'locked' ? '#e5e7eb' : '#99c9c2' }}"></div>
                        @endif
                        <button type="button" wire:click="setPhase('{{ $ph['key'] }}')"
                              class="inline-flex items-center gap-1 text-[12px] px-2 py-1 rounded-md cursor-pointer hover:bg-gray-50 transition-colors"
                              style="color:{{ $c }};{{ $ph['status'] === 'current' ? 'background:#f0fdfa;font-weight:600;' : '' }}{{ $ph['key'] === $activePhase ? 'box-shadow: inset 0 -2px 0 #0f766e;' : '' }}">
                            {{ $ph['status'] === 'done' ? '✓' : ($ph['status'] === 'current' ? '●' : '○') }}
                            {{ $ph['label'] }}
                        </button>
                    @endforeach
                </div>

                {{-- Hinweis, wenn man ein anderes als das aktuelle Gate ansieht --}}
                @if($activePhase !== $health['current'])
                    <div class="text-[11px] text-gray-500 mb-3 -mt-1">
                        Du siehst das Werkzeug für „{{ $activePhaseLabel }}".
                        <button type="button" wire:click="setPhase('{{ $health['current'] }}')" class="hover:underline" style="color:#0f766e">→ zurück zum aktuellen Zug</button>
                    </div>
                @endif

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

            @if(in_array($activePhase, ['konvertieren']))
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

            @endif

            @if($activePhase === 'verteilen')
            {{-- Rollen im Verbund — Fundament der Orchestrierung: wer spielt welche Rolle --}}
            <div class="mb-8">
                <h2 class="text-[13px] font-semibold text-gray-700">Rollen im Verbund</h2>
                <p class="text-[11px] text-gray-400 mb-3 max-w-2xl">Bevor Themen verteilt werden: <span class="font-medium">Brand/Spoke</span> besitzt differenzierte Themen · <span class="font-medium">Hub/Pillar</span> sammelt zentrale Kopf-Nachfrage &amp; verlinkt nach unten · <span class="font-medium">außerhalb</span> = anderes Feld (Agentur/Admin), spielt nicht mit.</p>
                <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
                    <table class="w-full text-[12px]" style="min-width:520px">
                        <thead>
                            <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                <th class="text-left px-3 py-2">Property</th>
                                <th class="text-right px-3 py-2">Sichtbarkeit</th>
                                <th class="text-left px-3 py-2">Rolle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($members as $u)
                                <tr class="border-b border-gray-50 last:border-0">
                                    <td class="px-3 py-2"><a href="{{ route('seo.urls.show', $u->id) }}" wire:navigate class="text-gray-700 hover:underline truncate block" style="max-width:280px">{{ $u->display_label }}</a></td>
                                    <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ number_format($u->visibility_score, 0) }}</td>
                                    <td class="px-3 py-2">
                                        <select x-on:change="$wire.setUrlRole({{ $u->id }}, $event.target.value)" class="text-[12px] border border-gray-300 rounded px-2 py-1 bg-white">
                                            <option value="" @selected(! $u->federation_role)>— Rolle —</option>
                                            @foreach(config('seo.federation_roles') as $rk => $r)
                                                <option value="{{ $rk }}" @selected($u->federation_role === $rk)>{{ $r['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if($activePhase === 'verteilen')
            {{-- Orchestrierungs-Board: Thema × Property (Owner küren, Kannibalisierung, Pillar) --}}
            <div class="mb-8">
                @include('seo::partials.wirkungsraum-board', ['board' => $board])
            </div>
            @endif

            @if(in_array($activePhase, ['ordnen', 'verteilen']))
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

            @endif

            @if($activePhase === 'ordnen')
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
                            @if(($portfolio->clustering_status ?? null) === 'completed' && $cr && empty($cr['error']) && ! empty($cr['merged']))
                                <p class="text-[11px] mt-1.5" style="color:#15803d">✓ In bestehenden Cluster „{{ $cr['clusters'][0]['name'] ?? '—' }}" übernommen · {{ (int) ($cr['keywords_merged'] ?? 0) }} Keywords ergänzt (ein Thema = ein Cluster).</p>
                            @elseif(($portfolio->clustering_status ?? null) === 'completed' && $cr && empty($cr['error']))
                                <p class="text-[11px] mt-1.5" style="color:#15803d">✓ {{ (int) ($cr['clusters_created'] ?? 0) }} neue Cluster · {{ (int) ($cr['keywords_clustered'] ?? 0) }} Keywords geordnet · {{ (int) ($cr['singletons_remaining'] ?? 0) }} Einzelgänger übrig</p>
                            @elseif(($portfolio->clustering_status ?? null) === 'failed' || ($cr && ! empty($cr['error'])))
                                <p class="text-[11px] mt-1.5" style="color:#b91c1c">Nach-Clustern fehlgeschlagen{{ $cr && ! empty($cr['error']) ? ': ' . $cr['error'] : '' }}.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            @endif

            @if(in_array($activePhase, ['ordnen', 'verteilen']))
            {{-- Semantische Karte — die Wirkungsraum-Linse auf die Keyword-Bedeutungen (Slice 2) --}}
            <div class="mb-8" {{ (($semantic['status'] ?? null) === 'running' || ($portfolio->clustering_status ?? null) === 'running') ? 'wire:poll.5s' : '' }}>
                <div class="flex items-start justify-between gap-3 mb-1">
                    <h2 class="text-[13px] font-semibold text-gray-700">Semantische Karte</h2>
                    <div class="flex items-center gap-1 shrink-0">
                        <a href="{{ route('seo.portfolios.kosmos', $portfolio) }}"
                           class="text-[11px] px-2.5 py-1 rounded border border-gray-300 text-gray-700 hover:bg-gray-50 mr-1" title="Die Themen als 3D-Kosmos erkunden">🌌 Kosmos</a>
                        <button wire:click="buildSemanticMap('both')" wire:loading.attr="disabled" @disabled(($semantic['status'] ?? null) === 'running')
                                class="text-[11px] px-2.5 py-1 rounded disabled:opacity-50 {{ $semanticSource === 'both' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-600' }}" title="Eigene + Wettbewerber-Keywords zusammen — Besitz-Mix je Feld (ordnen + erobern)">beide</button>
                        <button wire:click="buildSemanticMap('own')" wire:loading.attr="disabled" @disabled(($semantic['status'] ?? null) === 'running')
                                class="text-[11px] px-2.5 py-1 rounded disabled:opacity-50 {{ $semanticSource === 'own' ? 'bg-gray-900 text-white' : 'border border-gray-300 text-gray-600' }}" title="Nur eigene Keywords — ohne die Wettbewerber-Lücke">nur eigene</button>
                    </div>
                </div>
                <p class="text-[11px] text-gray-400 mb-2 max-w-2xl">Die Keywords nach <span class="font-medium">Bedeutung</span> geordnet — Vorschlag, kein SERP. Jedes Feld zeigt den <span class="font-medium">Besitz-Mix</span>: <span style="color:#0f766e">eigen</span> (rankt schon → ordnen) vs. <span style="color:#e11d48">Lücke</span> (nur Wettbewerber ranken → erobern). Einen <span class="font-medium">Cluster übernehmen</span> lässt SERP prüfen und macht einen echten Cluster (umkehrbar). Nichts gespeichert, bis du übernimmst.</p>
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
                        {{-- Vollständige Themenfeld-Tabelle: alle Nachbarschaften, chance-sortiert. Klick = Cluster ausklappen. --}}
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-2">
                            <div class="flex items-center justify-between px-3 py-2 bg-gray-50 border-b border-gray-100 text-[10px] uppercase tracking-wide text-gray-400">
                                <span>Themenfelder &amp; Cluster ({{ count($sm['neighborhoods']) }})</span>
                                <span class="tabular-nums">KWs · Cluster · Pot · IST · ↑Chance</span>
                            </div>
                            @foreach($sm['neighborhoods'] as $nbIdx => $nb)
                                <div x-data="{open:false}" class="border-b border-gray-50 last:border-0">
                                    <div x-on:click="open=!open" class="flex items-center justify-between gap-3 px-3 py-2 cursor-pointer hover:bg-gray-50">
                                        <span class="flex items-center gap-1.5 min-w-0">
                                            <span class="text-gray-400 text-[10px] w-3 shrink-0" x-text="open?'▾':'▸'"></span>
                                            @if(! empty($nb['rooms']))<span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-teal-100 text-teal-700 shrink-0">Themenfeld</span>@endif
                                            <span class="text-[12px] font-medium text-gray-700 truncate">{{ $nb['label'] }}</span>
                                            @if(! empty($nb['is_opportunity']))<span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-rose-100 text-rose-700 shrink-0">Chance</span>@endif
                                        </span>
                                        <span class="text-[11px] text-gray-500 tabular-nums shrink-0 flex gap-3">
                                            @php($nbOwn = ($nb['size'] ?? 0) - ($nb['comp_count'] ?? 0))
                                            <span class="inline-flex items-center gap-1.5" title="{{ $nbOwn }} eigen · {{ $nb['comp_count'] ?? 0 }} Wettbewerber-Lücke (von {{ $nb['size'] }} KW)">
                                                <span class="inline-block h-1.5 w-10 rounded-full overflow-hidden align-middle" style="background:#fecdd3">
                                                    <span class="block h-full" style="background:#0f766e;width:{{ ($nb['size'] ?? 0) > 0 ? round($nbOwn / $nb['size'] * 100) : 0 }}%"></span>
                                                </span>
                                                <span class="tabular-nums"><span style="color:#0f766e">{{ $nbOwn }}</span><span class="text-gray-300">/</span><span style="color:#e11d48">{{ $nb['comp_count'] ?? 0 }}</span></span>
                                            </span>
                                            <span class="text-gray-400">{{ ! empty($nb['rooms']) ? count($nb['rooms']).' Cl' : '—' }}</span>
                                            <span>Pot {{ number_format($nb['potenzial'] ?? 0) }}</span>
                                            <span>IST {{ number_format($nb['ist'] ?? 0) }}</span>
                                            <span class="font-medium" style="color:#e11d48">↑{{ number_format($nb['gap'] ?? 0) }}</span>
                                        </span>
                                    </div>

                                    <div x-show="open" style="display:none" class="bg-gray-50/40 border-t border-gray-100 px-2 py-1.5">
                                        @if(! empty($nb['rooms']))
                                            @if(! empty($nb['subquarters']))
                                                {{-- Mega-Themenfeld → Firmen-Sub-Felder (Verbund-Felder), je aufklappbar zur Cluster-Tabelle --}}
                                                @foreach($nb['subquarters'] as $sq)
                                                    <div x-data="{ sub: false }" class="border-b border-gray-100 last:border-0">
                                                        <div x-on:click="sub = ! sub" class="flex items-center justify-between gap-2 px-2 py-1.5 cursor-pointer hover:bg-white">
                                                            <span class="flex items-center gap-1.5 text-[11px] min-w-0">
                                                                <span class="text-gray-400 text-[9px] w-3 shrink-0" x-text="sub ? '▾' : '▸'"></span>
                                                                <span class="px-1.5 py-0.5 rounded text-white text-[10px] shrink-0" style="background:#6366f1">🏢 {{ $sq['domain'] }}</span>
                                                                <span class="text-gray-500">{{ $sq['count'] }} Cluster</span>
                                                            </span>
                                                            <span class="text-[10px] text-gray-500 tabular-nums shrink-0 flex gap-3">
                                                                <span>{{ $sq['size'] }} KW</span>
                                                                <span>Pot {{ number_format($sq['potenzial']) }}</span>
                                                                <span class="font-medium text-gray-700">Score {{ number_format($sq['score']) }}</span>
                                                            </span>
                                                        </div>
                                                        <div x-show="sub" style="display:none" class="pb-2">
                                                            @include('seo::partials.zimmer-table', ['nbIdx' => $nbIdx, 'rooms' => $nb['rooms'], 'indices' => $sq['room_indices']])
                                                        </div>
                                                    </div>
                                                @endforeach
                                            @else
                                                @include('seo::partials.zimmer-table', ['nbIdx' => $nbIdx, 'rooms' => $nb['rooms'], 'indices' => array_keys($nb['rooms'])])
                                            @endif
                                        @else
                                            <div class="flex items-center justify-between gap-3 px-2 py-1.5">
                                                <div class="flex flex-wrap gap-1 min-w-0">
                                                    @foreach(array_slice($nb['keywords'], 0, 8) as $kw)
                                                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600" title="Vol {{ number_format($kw['volume']) }}">@if(($kw['origin'] ?? 'own') === 'competitor')<span style="color:#e11d48">◆</span> @endif{{ $kw['keyword'] }}</span>
                                                    @endforeach
                                                    @if($nb['size'] > 8)<span class="text-[10px] text-gray-400">+{{ $nb['size'] - 8 }}</span>@endif
                                                </div>
                                                <span class="shrink-0 whitespace-nowrap">
                                                    <button wire:click="openSimple({{ $nbIdx }})" class="text-[11px] text-gray-400 hover:text-gray-700 mr-1.5">Details</button>
                                                    <button wire:click="rememberSimple({{ $nbIdx }})" class="text-[11px] text-gray-500 hover:text-gray-800 mr-1.5" title="als Kandidaten-Cluster merken (ohne SERP)">merken</button>
                                                    @if(! empty($nb['near_cluster']))
                                                        <button wire:click="integrateSimple({{ $nbIdx }}, {{ $nb['near_cluster']['id'] }})" class="text-[11px] px-2 py-0.5 rounded bg-teal-600 text-white mr-1.5" title="in „{{ $nb['near_cluster']['name'] }}" ({{ round($nb['near_cluster']['sim'] * 100) }}%) integrieren">integrieren</button>
                                                    @endif
                                                    @if(! empty($nb['company']))
                                                        <button wire:click="assignSimpleToCompany({{ $nbIdx }}, @js($nb['company']['domain']))" class="text-[11px] px-2 py-0.5 rounded text-white mr-1.5" style="background:#6366f1" title="zu {{ $nb['company']['domain'] }} ({{ round($nb['company']['sim'] * 100) }}%) zuordnen">→ {{ $nb['company']['domain'] }}</button>
                                                    @endif
                                                    <button wire:click="adoptSimple({{ $nbIdx }})" wire:loading.attr="disabled" @disabled(($portfolio->clustering_status ?? null) === 'running')
                                                            class="text-[11px] px-2 py-0.5 rounded bg-gray-900 text-white disabled:opacity-40 mr-1.5" title="SERP prüfen & als Cluster übernehmen">übernehmen</button>
                                                    <button wire:click="retireSimple({{ $nbIdx }})" class="text-[11px] text-gray-400 hover:text-rose-600" title="abstellen — Keywords stilllegen (umkehrbar)">abstellen</button>
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-[10px] text-gray-400 mb-4"><span class="text-teal-700 font-medium">Themenfeld</span> = großes Feld (klick = Cluster) · <span class="px-1 rounded bg-green-100 text-green-700">Weißraum</span> baubar · <span style="color:#8a7a63">Grau</span> Wettbewerber · <span style="color:#e11d48">↑Chance</span> = Mehr-Traffic/Mon (Pot − IST) · nach Chance sortiert · „übernehmen" prüft SERP → echter Cluster</p>
                    @endif

                    @if(! empty($sm['outliers']))
                        {{-- Ausreißer = Einzelgänger ohne Nachbarn. Aktionsfähig: routen (Firma) oder abstellen (Rausch). --}}
                        <div class="bg-white rounded-lg border border-gray-200 p-3 mb-8" style="max-width:640px">
                            <div class="flex items-baseline justify-between gap-2 mb-0.5">
                                <span class="text-[12px] font-medium text-gray-700">Ausreißer ({{ count($sm['outliers']) }})</span>
                                <button wire:click="retireOutliersWithoutCompany" class="text-[10px] text-gray-400 hover:text-rose-600" title="alle Einzelgänger ohne Firmen-Bezug stilllegen">alle ohne Firma abstellen</button>
                            </div>
                            <div class="text-[10px] text-gray-400 mb-2">Keine semantischen Nachbarn — passt zu einer Firma (zuordnen) oder zu keiner (abstellen).</div>
                            <div class="flex flex-col gap-1">
                                @foreach($sm['outliers'] as $kw)
                                    <div class="flex items-center justify-between gap-2 text-[11px]">
                                        <span class="text-gray-600 min-w-0" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="Vol {{ number_format($kw['volume']) }}">{{ $kw['keyword'] }}</span>
                                        <span class="shrink-0 whitespace-nowrap flex items-center gap-1.5">
                                            @if(! empty($kw['company']))
                                                <button wire:click="assignOutlierToCompany({{ $kw['id'] }}, @js($kw['company']['domain']))" class="text-[10px] px-1.5 py-0.5 rounded text-white" style="background:#6366f1" title="zu {{ $kw['company']['domain'] }} ({{ round($kw['company']['sim'] * 100) }}%) zuordnen">🏢 {{ $kw['company']['domain'] }}</button>
                                            @endif
                                            <button wire:click="retireOutlier({{ $kw['id'] }})" class="text-[10px] text-gray-400 hover:text-rose-600">abstellen</button>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(! empty($sm['anchor']))
                        <p class="text-[10px] text-gray-400 mt-2">Anker: {{ \Illuminate\Support\Str::limit($sm['anchor'], 140) }}</p>
                    @endif
                @else
                    <div class="bg-white rounded-lg border border-gray-200 p-4 text-[12px] text-gray-500">Noch keine Karte. Der Knopf liest die Keyword-Vektoren aus Qdrant und zeigt Nachbarschaften, Ausreißer und themenferne Keywords.</div>
                @endif
            </div>

            @endif

            {{-- Cluster-Detailansicht: Keywords + rankende URLs + Übernehmen --}}
            @if($showRoomDetail && $roomDetail)
                <div class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto" style="background:rgba(0,0,0,0.4)" wire:click="closeRoomDetail">
                    <div class="bg-white rounded-lg border border-gray-200 shadow-xl w-full max-w-3xl mt-10" wire:click.stop>
                        <div class="px-4 py-3 border-b border-gray-100 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-[14px] font-semibold text-gray-800">{{ $roomDetail['label'] }}</div>
                                <div class="text-[11px] text-gray-400 tabular-nums">{{ $roomDetail['size'] }} Keywords · Pot ~{{ number_format($roomDetail['potenzial']) }} / IST ~{{ number_format($roomDetail['ist']) }} · Chance <span style="color:#e11d48">↑{{ number_format($roomDetail['gap']) }}</span></div>
                            </div>
                            <button wire:click="closeRoomDetail" class="text-gray-400 hover:text-gray-700 text-[13px] shrink-0">schließen</button>
                        </div>

                        @if($roomDetail['situation'] === 'whitespace')
                            <div class="px-4 py-2 text-[12px] bg-rose-100 text-rose-700">Weißraum — keine eigene Seite rankt dafür. Chance: neue Seite bauen.</div>
                        @elseif($roomDetail['situation'] === 'cannibalization')
                            <div class="px-4 py-2 text-[12px] bg-amber-100 text-amber-700">Kannibalisierung — {{ $roomDetail['own_ranking'] }} eigene Seiten konkurrieren um dieses Thema. Auf eine Pillar-Seite konsolidieren.</div>
                        @else
                            <div class="px-4 py-2 text-[12px] bg-gray-100 text-gray-600">Eine eigene Seite rankt bereits — vertiefen.</div>
                        @endif

                        <div class="grid md:grid-cols-2">
                            <div class="p-4 border-r border-gray-100 overflow-y-auto" style="max-height:24rem">
                                <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-2">Keywords ({{ count($roomDetail['keywords']) }}) · Vol · IST-Pos</div>
                                <table class="w-full text-[12px]">
                                    <tbody>
                                        @foreach($roomDetail['keywords'] as $kw)
                                            <tr class="border-b border-gray-50 last:border-0">
                                                <td class="py-1 pr-2 text-gray-700">@if($kw['origin'] === 'competitor')<span style="color:#e11d48">◆</span> @endif{{ $kw['keyword'] }}</td>
                                                <td class="py-1 px-2 text-right text-gray-500 tabular-nums">{{ number_format($kw['volume']) }}</td>
                                                <td class="py-1 pl-2 text-right tabular-nums {{ $kw['position'] !== null ? 'text-gray-600' : 'text-gray-300' }}">{{ $kw['position'] !== null ? '#' . $kw['position'] : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-4 overflow-y-auto" style="max-height:24rem">
                                <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-2">Rankende URLs ({{ count($roomDetail['urls']) }})</div>
                                @if(empty($roomDetail['urls']))
                                    <div class="text-[12px] text-gray-400">Noch keine rankenden URLs erfasst.</div>
                                @else
                                    @foreach($roomDetail['urls'] as $u)
                                        <div class="flex items-baseline justify-between gap-2 py-1 border-b border-gray-50 last:border-0 text-[12px]">
                                            <span class="{{ $u['is_own'] ? 'text-gray-800 font-medium' : 'text-gray-500' }}" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">@if($u['is_own'])<span class="text-[9px] px-1 rounded bg-teal-100 text-teal-700">eigen</span> @endif{{ $u['domain'] }}{{ $u['path'] !== '/' ? $u['path'] : '' }}</span>
                                            <span class="text-gray-400 tabular-nums shrink-0 text-[11px]">{{ $u['kw'] }} KW · #{{ $u['best'] }}</span>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>

                        <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between gap-2">
                            <span class="text-[11px] text-gray-400">„Übernehmen" prüft per SERP und macht einen echten Cluster (umkehrbar).</span>
                            <div class="flex items-center gap-2 shrink-0">
                                <button wire:click="closeRoomDetail" class="text-[12px] px-3 py-1.5 rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50">Schließen</button>
                                <button wire:click="adoptFromDetail" wire:loading.attr="disabled" @disabled(($portfolio->clustering_status ?? null) === 'running')
                                        class="text-[12px] font-medium px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700 disabled:opacity-50">Übernehmen → Cluster</button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($activePhase === 'verteilen')
            {{-- Seiten-Gesundheit (Angebots-Achse): unfokussierte Seiten + konkrete Kannibalisierung --}}
            <div class="mb-8">
                <div class="flex items-baseline justify-between gap-3 mb-1">
                    <h2 class="text-[13px] font-semibold text-gray-700">Seiten-Gesundheit</h2>
                    <span class="text-[11px] text-gray-400">Angebots-Achse — bedient jede Seite ein Thema?</span>
                </div>
                <p class="text-[11px] text-gray-400 mb-3 max-w-2xl">Wo eine Seite zu viele Themen bedient (verwässert) oder mehrere eigene Seiten um dasselbe Keyword ranken (Kannibalisierung). Ziel: „ein Thema = eine Seite".</p>

                @if(empty($pageHealth['unfocused']) && empty($pageHealth['cannibalized']))
                    <div class="bg-white rounded-lg border border-gray-200 p-4 text-[12px] text-gray-500">✓ Keine unfokussierten Seiten und keine Kannibalisierung gefunden.</div>
                @else
                    @if(! empty($pageHealth['unfocused']))
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-3">
                            <div class="px-3 py-2 bg-gray-50 border-b border-gray-100 text-[10px] uppercase tracking-wide text-gray-400 flex justify-between">
                                <span>Unfokussierte Seiten ({{ count($pageHealth['unfocused']) }})</span>
                                <span>ranken für ≥ 3 Themen</span>
                            </div>
                            <table class="w-full text-[12px]">
                                <tbody>
                                    @foreach($pageHealth['unfocused'] as $p)
                                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50">
                                            <td class="px-3 py-2"><a href="{{ route('seo.urls.show', $p['url']) }}" wire:navigate class="text-[#166EE1] hover:underline">{{ $p['url']->path ?: '/' }}</a></td>
                                            <td class="px-3 py-2 text-right tabular-nums"><span class="font-semibold text-rose-600">{{ $p['cluster_count'] }}</span> <span class="text-gray-400">Themen</span></td>
                                            <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $p['kw_count'] }} KW</td>
                                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                                <a href="{{ route('seo.urls.show', $p['url']) }}" wire:navigate class="text-[11px] text-gray-500 hover:text-gray-800 mr-3">fokussieren →</a>
                                                @include('seo::partials.disposition-control', ['urlId' => $p['url']->id, 'disposition' => $p['url']->disposition])
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if(! empty($pageHealth['cannibalized']))
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <div class="px-3 py-2 bg-gray-50 border-b border-gray-100 text-[10px] uppercase tracking-wide text-gray-400 flex justify-between">
                                <span>Kannibalisierung ({{ count($pageHealth['cannibalized']) }})</span>
                                <span>mehrere eigene Seiten · ein Keyword</span>
                            </div>
                            <table class="w-full text-[12px]">
                                <tbody>
                                    @foreach($pageHealth['cannibalized'] as $c)
                                        <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50 align-top">
                                            <td class="px-3 py-2" style="white-space:nowrap;vertical-align:top"><span class="font-medium text-gray-700">{{ $c['keyword'] }}</span> <span class="text-[10px] text-gray-400 tabular-nums">Vol {{ number_format($c['volume']) }}</span></td>
                                            <td class="px-3 py-2">
                                                <div class="flex flex-col gap-1">
                                                    @foreach($c['urls'] as $ui => $u)
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-[11px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600">{{ $u['path'] }} <span class="text-gray-400">P{{ $u['position'] }}</span></span>
                                                            @if($ui === 0)
                                                                <span class="text-[10px] text-green-700" title="beste Position — behält das Thema">Owner</span>
                                                            @else
                                                                @include('seo::partials.disposition-control', ['urlId' => $u['url_id'], 'disposition' => $u['disposition']])
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </div>
            @endif

            @if($activePhase === 'verteilen')
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

            @endif

            <p class="mt-6 text-[11px] text-gray-400">Nächste Ausbaustufen: KI-Vorschläge in Aktionen (Cluster-Owner, Briefs) · Snapshots im Takt der Datensammlung.</p>
            @endif {{-- /$view !== 'messen' (Dashboard-Block) --}}

            @endif {{-- /Stationen ($station) --}}

            {{-- ===================== Bestand-Views ===================== --}}

            {{-- Entitäten (v2): besessene Entitäten + Multi-Surface-Präsenz + Share of Answer --}}
            @if($view === 'entities')
                @include('seo::partials.wirkungsraum-entities', ['entities' => $entities, 'entityFlash' => $entityFlash])
            @endif

            {{-- Keywords: alle Keywords, für die der Wirkungsraum rankt --}}
            @if($view === 'keywords')
                <div class="mb-2">
                    <h2 class="text-[13px] font-semibold text-gray-700">Keywords <span class="text-gray-400 font-normal">({{ $bestandKeywords->count() }}{{ $bestandKeywords->count() >= 200 ? '+' : '' }})</span></h2>
                    <p class="text-[11px] text-gray-400 mt-0.5">Alle Keywords, für die Mitglieder dieses Wirkungsraums ranken — beste Position zuerst.</p>
                </div>
                @if($bestandKeywords->isEmpty())
                    <div class="bg-white rounded-lg border border-gray-200 p-6 text-[12px] text-gray-500">Noch keine Keywords im Wirkungsraum.</div>
                @else
                    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
                        <table class="w-full text-[13px]" style="min-width:640px">
                            <thead>
                                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                    <th class="text-left px-4 py-2">Keyword</th>
                                    <th class="text-left px-4 py-2">Intent</th>
                                    <th class="text-left px-4 py-2">Cluster</th>
                                    <th class="text-right px-4 py-2">Volumen</th>
                                    <th class="text-right px-4 py-2">Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($bestandKeywords as $kw)
                                    @php($pos = $kw->urls->min('pivot.position'))
                                    <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                                        <td class="px-4 py-2 text-gray-700">{{ $kw->keyword }}</td>
                                        <td class="px-4 py-2">@if($kw->search_intent)<span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">{{ $kw->search_intent }}</span>@else<span class="text-gray-300">—</span>@endif</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $kw->cluster->name ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format((int) $kw->search_volume) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums {{ $pos !== null && $pos <= 10 ? 'font-semibold text-green-700' : 'text-gray-600' }}">{{ $pos !== null ? number_format($pos, 0) : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endif

            {{-- Cluster: Durchdringung je Cluster (IST rankend / SOLL) --}}
            @if($view === 'clusters')
                <div class="mb-2">
                    <h2 class="text-[13px] font-semibold text-gray-700">Cluster</h2>
                    <p class="text-[11px] text-gray-400 mt-0.5">Die Themen dieses Wirkungsraums — Durchdringung IST (rankend) gegen SOLL.</p>
                </div>
                @if($penetration['clusters']->isNotEmpty() || $penetration['unclustered'])
                    @include('seo::partials.scope-penetration', ['clusters' => $penetration['clusters'], 'coverage' => $coverage])
                @else
                    <div class="bg-white rounded-lg border border-gray-200 p-6 text-[12px] text-gray-500">Noch keine Cluster — in der Station „Ordnen" bauen.</div>
                @endif
            @endif

            {{-- Wettbewerber: der Markt um den Verbund --}}
            @if($view === 'competitors')
                <div class="mb-2">
                    <h2 class="text-[13px] font-semibold text-gray-700">Wettbewerber</h2>
                    <p class="text-[11px] text-gray-400 mt-0.5">Der Markt um den Wirkungsraum — wer rankt für dieselben Themen.</p>
                </div>
                @if($competitors->isNotEmpty())
                    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
                        <table class="w-full text-[13px]" style="min-width:480px">
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
                @else
                    <div class="bg-white rounded-lg border border-gray-200 p-6 text-[12px] text-gray-500">Noch keine Wettbewerber erfasst.</div>
                @endif
            @endif
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
