<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Rankings" icon="heroicon-o-chart-bar" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'URLs', 'route' => 'seo.urls'],
            ['label' => ($seoUrl->path && $seoUrl->path !== '/') ? Str::limit($seoUrl->path, 20) : $seoUrl->domain, 'href' => route('seo.urls.show', $seoUrl)],
            ['label' => 'Rankings'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'urls'])
    </x-slot>

    <x-ui-page-container>

        {{-- Intro --}}
        <p class="text-[13px] text-gray-500 mb-6">Ranking-Verlauf für diese URL und ihre Unterseiten. Verfolge, wie sich deine Positionen in Google über die Zeit verändern. Gewinner und Verlierer zeigen, welche Keywords sich verbessern oder verschlechtern.</p>

        {{-- Period Selector --}}
        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5 w-fit mb-6">
            @foreach([7 => '7 Tage', 14 => '14 Tage', 30 => '30 Tage', 90 => '90 Tage'] as $days => $label)
                <button wire:click="setPeriod({{ $days }})"
                        class="px-3 py-1.5 text-[12px] rounded-md transition-colors {{ $periodDays === $days ? 'bg-white text-gray-900 font-medium shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Summary Stats --}}
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-arrow-trending-up', 'w-4 h-4 text-green-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Aufsteiger</span>
                </div>
                <div class="text-2xl font-bold text-green-600 tabular-nums">{{ $trends['summary']['rising_count'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Keywords mit verbesserter Position</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-arrow-trending-down', 'w-4 h-4 text-red-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Absteiger</span>
                </div>
                <div class="text-2xl font-bold text-red-600 tabular-nums">{{ $trends['summary']['falling_count'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Keywords mit verschlechterter Position</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-minus', 'w-4 h-4 text-gray-400')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Stabil</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $trends['summary']['stable_count'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Unveränderte Positionen</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-sparkles', 'w-4 h-4 text-blue-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Neu</span>
                </div>
                <div class="text-2xl font-bold text-blue-600 tabular-nums">{{ $trends['summary']['new_entries_count'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Erstmalig rangende Keywords</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-question-mark-circle', 'w-4 h-4 text-gray-400')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Keine Daten</span>
                </div>
                <div class="text-2xl font-bold text-gray-400 tabular-nums">{{ $trends['summary']['no_data_count'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Noch nicht getrackt</div>
            </div>
        </div>

        {{-- Position Distribution Chart --}}
        @if(!empty($positionDistribution))
            <div class="bg-white rounded-lg border border-gray-200 p-6 mb-8">
                <h3 class="text-[13px] font-semibold text-gray-900 mb-1">Positions-Verteilung</h3>
                <p class="text-[11px] text-gray-400 mb-4">Aktuelle Verteilung deiner Keywords über die Google-Ergebnisseiten. Top 3 und Top 10 bringen den meisten Traffic.</p>
                @php
                    $distData = json_encode(array_values($positionDistribution));
                    $distLabels = json_encode(array_keys($positionDistribution));
                @endphp
                <div wire:ignore x-data x-init="$nextTick(() => {
                    if (typeof ApexCharts !== 'undefined') {
                        new ApexCharts($el, {
                            chart: { type: 'bar', height: 200, toolbar: { show: false }, fontFamily: 'inherit' },
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
                })" style="height: 200px;"></div>
            </div>
        @endif

        {{-- Filter --}}
        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5 w-fit mb-4">
            @foreach(['all' => 'Alle', 'winners' => 'Gewinner', 'losers' => 'Verlierer'] as $type => $label)
                <button wire:click="setFilterType('{{ $type }}')"
                        class="px-3 py-1.5 text-[12px] rounded-md transition-colors {{ $filterType === $type ? 'bg-white text-gray-900 font-medium shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Rankings Table --}}
        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                        <th class="px-4 py-2.5 text-left">URL</th>
                        <th class="px-4 py-2.5 text-left">Keyword</th>
                        <th class="px-4 py-2.5 text-right">Position</th>
                        <th class="px-4 py-2.5 text-right">Veränderung</th>
                        <th class="px-4 py-2.5 text-left">SERP Features</th>
                        <th class="px-4 py-2.5 text-right">Datum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rankings as $entry)
                        <tr wire:key="rank-{{ $entry->id }}" class="hover:bg-gray-50 transition-colors">
                            <td class="px-4 py-2.5">
                                @if($entry->url)
                                    <a href="{{ route('seo.urls.show', $entry->url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-[200px]">
                                        {{ $entry->url->path ?: '/' }}
                                    </a>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ $entry->keyword?->keyword ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @include('seo::partials.position-badge', ['position' => $entry->position, 'change' => $entry->position_delta])
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @if($entry->position_delta !== null)
                                    <span class="{{ $entry->position_delta > 0 ? 'text-green-600' : ($entry->position_delta < 0 ? 'text-red-600' : 'text-gray-400') }} font-medium text-[12px]">
                                        {{ $entry->position_delta > 0 ? '+' : '' }}{{ $entry->position_delta }}
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($entry->serp_features)
                                    @foreach((array)$entry->serp_features as $feature)
                                        <span class="inline-block px-1.5 py-0.5 bg-gray-100 rounded text-[10px] text-gray-600 mr-1">{{ $feature }}</span>
                                    @endforeach
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-[11px] text-gray-400 tabular-nums">{{ $entry->tracked_at?->format('d.m.Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center">
                                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                    @svg('heroicon-o-chart-bar', 'w-5 h-5 text-gray-400')
                                </div>
                                <p class="text-sm text-gray-500 font-medium mb-1">Keine Ranking-Daten</p>
                                <p class="text-xs text-gray-400">Für diesen Zeitraum liegen keine Ranking-Änderungen vor. Die Daten werden bei jedem SERP-Check aktualisiert.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($rankings->hasPages())
            <div class="mt-4">{{ $rankings->links() }}</div>
        @endif

    </x-ui-page-container>
</x-ui-page>
