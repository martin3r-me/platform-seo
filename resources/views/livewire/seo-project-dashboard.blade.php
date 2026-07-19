<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="SEO Dashboard" icon="heroicon-o-magnifying-glass-circle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle'],
            ['label' => 'Dashboard'],
        ]">
            <x-ui-button variant="secondary" size="sm" wire:click="openSettingsModal">
                @svg('heroicon-o-cog-6-tooth', 'w-4 h-4')
                <span>Einstellungen</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.help-banner', ['lens' => 'dashboard'])

        {{-- Intro --}}
        <div class="mb-4">
            <p class="text-[13px] text-gray-500">Gesamtübersicht deiner SEO-Performance. Hier siehst du auf einen Blick, wie viele URLs und Keywords getrackt werden, wie sichtbar deine Seiten in Google sind und ob das API-Budget im Rahmen bleibt.</p>
        </div>

        {{-- Strategie-KPIs --}}
        @php
            $healthColor = match(true) {
                $avgClusterHealth === null => 'text-gray-300',
                $avgClusterHealth >= 70 => 'text-green-600',
                $avgClusterHealth >= 40 => 'text-amber-600',
                default => 'text-red-500',
            };
        @endphp
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
            <a href="{{ route('seo.recommendations') }}" wire:navigate
               class="group bg-white rounded-lg border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-sm transition-all">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-light-bulb', 'w-4 h-4 ' . ($openRecommendations > 0 ? 'text-amber-500' : 'text-gray-400'))
                        <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Offene Empfehlungen</span>
                    </div>
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-gray-300 group-hover:text-indigo-400 transition-colors')
                </div>
                <div class="text-3xl font-bold tabular-nums {{ $openRecommendations > 0 ? 'text-gray-900' : 'text-gray-300' }}">{{ $openRecommendations }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Konkrete Handlungen aus der Engine</div>
            </a>

            <a href="{{ route('seo.clusters') }}" wire:navigate
               class="group bg-white rounded-lg border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-sm transition-all">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-indigo-500')
                        <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Cluster</span>
                    </div>
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-gray-300 group-hover:text-indigo-400 transition-colors')
                </div>
                <div class="text-3xl font-bold text-gray-900 tabular-nums">{{ number_format($clusterCount) }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Strategische Themen-Einheiten</div>
            </a>

            <a href="{{ route('seo.clusters') }}" wire:navigate
               class="group bg-white rounded-lg border border-gray-200 p-5 hover:border-indigo-300 hover:shadow-sm transition-all">
                <div class="flex items-center justify-between mb-1">
                    <div class="flex items-center gap-2">
                        @svg('heroicon-o-heart', 'w-4 h-4 text-rose-400')
                        <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Ø Cluster-Health</span>
                    </div>
                    @svg('heroicon-o-arrow-right', 'w-4 h-4 text-gray-300 group-hover:text-indigo-400 transition-colors')
                </div>
                <div class="text-3xl font-bold tabular-nums {{ $healthColor }}">{{ $avgClusterHealth ?? '—' }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Abdeckung × Sichtbarkeit über alle Cluster</div>
            </a>
        </div>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-8">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-globe-alt', 'w-4 h-4 text-indigo-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">URLs</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $urlCounts['total'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Alle registrierten Seiten</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-home', 'w-4 h-4 text-blue-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Eigene URLs</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $urlCounts['own'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Deine Domains</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-user-group', 'w-4 h-4 text-amber-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Wettbewerber</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $urlCounts['competitor'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Konkurrenz-Seiten</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-key', 'w-4 h-4 text-gray-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Keywords</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($keywordCount) }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Getrackte Suchbegriffe</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-eye', 'w-4 h-4 text-green-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Sichtbarkeit</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $visibility['percentage'] }}<span class="text-sm font-normal text-gray-400">%</span></div>
                <div class="text-[10px] text-gray-400 mt-1">Google-Sichtbarkeitsindex</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-banknotes', 'w-4 h-4 {{ ($budgetSummary["percentage"] ?? 0) > 80 ? "text-red-500" : "text-gray-500" }}')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Budget</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $budgetSummary['percentage'] ?? 0 }}<span class="text-sm font-normal text-gray-400">{{ $budgetSummary['percentage'] !== null ? '%' : '' }}</span></div>
                <div class="text-[10px] text-gray-400 mt-1">API-Nutzung diesen Monat</div>
            </div>
        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {{-- Position Distribution --}}
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-[13px] font-semibold text-gray-900">Positions-Verteilung</h3>
                </div>
                <p class="text-[11px] text-gray-400 mb-4">Wie viele deiner Keywords in welchem Positionsbereich ranken. Ziel: möglichst viele in den Top 10.</p>
                @php
                    $distData = json_encode(array_values($positionDistribution));
                    $distLabels = json_encode(array_keys($positionDistribution));
                @endphp
                <div wire:ignore x-data x-init="$nextTick(() => {
                    if (typeof ApexCharts !== 'undefined') {
                        new ApexCharts($el, {
                            chart: { type: 'bar', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
                            series: [{ name: 'Keywords', data: {{ $distData }} }],
                            xaxis: { categories: {{ $distLabels }}, labels: { style: { fontSize: '11px', colors: '#9ca3af' } } },
                            yaxis: { labels: { style: { fontSize: '11px', colors: '#9ca3af' } } },
                            colors: ['#2ecc71', '#27ae60', '#f39c12', '#e67e22', '#e74c3c'],
                            plotOptions: { bar: { distributed: true, borderRadius: 4, columnWidth: '60%' } },
                            grid: { borderColor: '#f3f4f6', strokeDashArray: 3 },
                            legend: { show: false },
                            dataLabels: { enabled: true, style: { fontSize: '11px', fontWeight: 600 } },
                        }).render();
                    }
                })" style="height: 220px;"></div>
            </div>

            {{-- Visibility History --}}
            <div class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex items-center justify-between mb-1">
                    <h3 class="text-[13px] font-semibold text-gray-900">Sichtbarkeits-Verlauf (30 Tage)</h3>
                </div>
                <p class="text-[11px] text-gray-400 mb-4">Tägliche Gesamtsichtbarkeit deiner eigenen URLs. Steigende Kurve = bessere Rankings in Google.</p>
                @if(!empty($visibilityHistory))
                    <div wire:key="vis-history">
                        @include('seo::partials.sparkline', ['data' => $visibilityHistory, 'color' => '#6366f1', 'height' => 220, 'type' => 'area'])
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center h-[220px] text-center">
                        @svg('heroicon-o-chart-bar', 'w-8 h-8 text-gray-300 mb-2')
                        <p class="text-[12px] text-gray-400">Noch keine Snapshot-Daten. Die Sichtbarkeit wird täglich berechnet, sobald Ranking-Daten vorliegen.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Top URLs --}}
        @if($topUrls->isNotEmpty())
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-8">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h3 class="text-[13px] font-semibold text-gray-900">Top URLs nach Sichtbarkeit</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Die sichtbarsten Seiten in deinem Portfolio. Hohe Sichtbarkeit = viele relevante Rankings mit gutem Suchvolumen.</p>
                    </div>
                    <a href="{{ route('seo.urls') }}" wire:navigate class="text-[12px] text-indigo-600 hover:underline font-medium">Alle URLs</a>
                </div>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                            <th class="px-5 py-2.5 text-left">URL</th>
                            <th class="px-4 py-2.5 text-right">Keywords</th>
                            <th class="px-4 py-2.5 text-right">SV</th>
                            <th class="px-4 py-2.5 text-right">Sichtbarkeit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($topUrls as $url)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-2.5">
                                    <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-md font-medium">
                                        {{ ($url->path && $url->path !== '/') ? $url->path : $url->domain }}
                                    </a>
                                    @if($url->path && $url->path !== '/')
                                        <span class="text-[10px] text-gray-400">{{ $url->domain }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ $url->keyword_count }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @include('seo::partials.sv-badge', ['volume' => $url->total_search_volume])
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="font-semibold text-gray-900 tabular-nums">{{ number_format($url->visibility_score, 1) }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Recent Signals --}}
        @if($recentSignals->isNotEmpty())
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-[13px] font-semibold text-gray-900">Aktuelle Signale</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Automatisch erkannte Veränderungen: Ranking-Sprünge, Traffic-Einbrüche, neue Chancen. Reagiere frühzeitig auf negative Trends.</p>
                    </div>
                </div>
                <div class="space-y-2">
                    @foreach($recentSignals as $signal)
                        @php
                            $dotColor = match($signal->severity) {
                                'critical' => 'bg-red-500',
                                'warning' => 'bg-amber-500',
                                'watch' => 'bg-blue-500',
                                'opportunity' => 'bg-green-500',
                                default => 'bg-gray-400',
                            };
                            $borderColor = match($signal->severity) {
                                'critical' => 'border-l-red-400',
                                'warning' => 'border-l-amber-400',
                                'watch' => 'border-l-blue-400',
                                'opportunity' => 'border-l-green-400',
                                default => 'border-l-gray-300',
                            };
                        @endphp
                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-200 border-l-4 {{ $borderColor }}">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $dotColor }}"></span>
                            <div class="min-w-0 flex-1">
                                <span class="text-[13px] text-gray-700 block">{{ $signal->title }}</span>
                                <div class="flex items-center gap-2 text-[11px] text-gray-400 mt-0.5">
                                    <span>{{ $signal->detected_at->format('d.m.Y') }}</span>
                                    @if($signal->url)
                                        <a href="{{ route('seo.urls.show', $signal->url) }}" wire:navigate class="text-indigo-500 hover:underline truncate">{{ $signal->url->path }}</a>
                                    @endif
                                </div>
                            </div>
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 rounded text-gray-500 shrink-0">{{ str_replace('_', ' ', $signal->signal_type) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </x-ui-page-container>

    {{-- Settings Modal --}}
    <x-ui-modal wire:model="showSettingsModal" title="SEO Einstellungen">
        <form wire:submit="saveSettings">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                    <input type="text" wire:model="editDomain" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="beispiel.de">
                    <p class="text-[11px] text-gray-400 mt-1">Die Haupt-Domain für die Sichtbarkeitsberechnung.</p>
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showSettingsModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">Speichern</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>
</x-ui-page>
