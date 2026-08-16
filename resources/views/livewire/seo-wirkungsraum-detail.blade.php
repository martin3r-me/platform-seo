<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$wirkungsraum->name" icon="heroicon-o-rocket-launch" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wirkungsräume', 'route' => 'seo.wirkungsraeume'],
            ['label' => $wirkungsraum->name],
        ]" />
    </x-slot>

    <x-ui-page-container>
        <div class="max-w-5xl">
            {{-- Kopf --}}
            <div class="flex items-start justify-between gap-4 mb-4">
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-gray-900">{{ $wirkungsraum->name }}</h1>
                    @if($wirkungsraum->goal)
                        <p class="text-[13px] text-gray-500 mt-0.5">🎯 {{ $wirkungsraum->goal }}</p>
                    @endif
                </div>
                <button wire:click="openAddUrls" class="shrink-0 text-[13px] font-medium px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">
                    + URLs hinzufügen
                </button>
            </div>

            {{-- Aggregat-KPIs --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
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
                                <td class="px-4 py-2.5">
                                    <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline font-medium">{{ $url->domain }}{{ $url->path !== '/' ? $url->path : '' }}</a>
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ number_format($url->keyword_count) }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ number_format($url->total_search_volume) }}</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-900 tabular-nums">{{ number_format($url->visibility_score, 0) }}</td>
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
                    <h2 class="text-[13px] font-semibold text-gray-700 mb-1">Durchdringung je Cluster</h2>
                    <p class="text-[11px] text-gray-400 mb-3">IST (ranken) von SOLL (Ziel laut Cluster). Höher = Thema tiefer besetzt.</p>
                    @if($penetration['clusters']->isNotEmpty())
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
                                    @foreach($penetration['clusters'] as $c)
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
                    @if($penetration['unclustered'])
                        <div class="mt-3 bg-white rounded-lg border border-dashed border-gray-200 px-4 py-3 flex items-center justify-between">
                            <span class="text-[12px] text-gray-600">Ungeclusterter Rest: <span class="font-medium">{{ number_format($penetration['unclustered']['soll']) }}</span> Keywords, davon <span class="font-medium">{{ number_format($penetration['unclustered']['ist']) }}</span> wild rankend</span>
                            <span class="text-[11px] text-gray-400">→ clustern zum Ordnen</span>
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

            <p class="mt-6 text-[11px] text-gray-400">Nächste Ausbaustufen: Entwicklung über Zeit · KI-Verteilung.</p>
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
