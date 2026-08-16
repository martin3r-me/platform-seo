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
                    <button wire:click="analyze" wire:target="analyze" wire:loading.attr="disabled"
                            class="text-[13px] font-medium px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                        <span wire:loading.remove wire:target="analyze">🤖 Verteilung vorschlagen</span>
                        <span wire:loading wire:target="analyze">Analysiere…</span>
                    </button>
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
            <p class="text-[11px] text-gray-400 mb-6">Zahlen auf Property-Ebene — jede Mitglieds-URL inkl. ihrer eigenen Unterseiten, über den Verbund dedupliziert (deckungsgleich mit der URL-Detailseite).</p>

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
