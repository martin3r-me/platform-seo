<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Rankings" icon="heroicon-o-chart-bar" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Rankings'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'rankings'])
    </x-slot>

    <x-ui-page-container>

        {{-- Period Selector --}}
        <div class="flex items-center gap-2 mb-6">
            @foreach([7 => '7 Tage', 14 => '14 Tage', 30 => '30 Tage', 90 => '90 Tage'] as $days => $label)
                <button wire:click="setPeriod({{ $days }})"
                        class="px-3 py-1.5 text-sm rounded-lg {{ $periodDays === $days ? 'bg-indigo-50 text-indigo-600 font-medium' : 'text-gray-500 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Summary Stats --}}
        <x-ui-stats-grid :cols="5">
            <x-ui-dashboard-tile title="Aufsteiger" :count="$trends['summary']['rising_count']" icon="arrow-trending-up" variant="success" />
            <x-ui-dashboard-tile title="Absteiger" :count="$trends['summary']['falling_count']" icon="arrow-trending-down" variant="danger" />
            <x-ui-dashboard-tile title="Stabil" :count="$trends['summary']['stable_count']" icon="minus" variant="neutral" />
            <x-ui-dashboard-tile title="Neu" :count="$trends['summary']['new_entries_count']" icon="sparkles" variant="info" />
            <x-ui-dashboard-tile title="Keine Daten" :count="$trends['summary']['no_data_count']" icon="question-mark-circle" variant="neutral" />
        </x-ui-stats-grid>

        {{-- Position Distribution Chart --}}
        @if(!empty($positionDistribution))
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h3 class="text-sm font-medium text-gray-700 mb-4">Positions-Verteilung</h3>
                @php
                    $distChartId = 'ranking-dist-' . uniqid();
                    $distData = json_encode(array_values($positionDistribution));
                    $distLabels = json_encode(array_keys($positionDistribution));
                @endphp
                <div id="{{ $distChartId }}" style="height: 200px;" wire:ignore></div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        if (typeof ApexCharts !== 'undefined') {
                            new ApexCharts(document.querySelector('#{{ $distChartId }}'), {
                                chart: { type: 'bar', height: 200, toolbar: { show: false } },
                                series: [{ name: 'Keywords', data: {!! $distData !!} }],
                                xaxis: { categories: {!! $distLabels !!} },
                                colors: ['#2ecc71', '#27ae60', '#f39c12', '#e67e22', '#e74c3c'],
                                plotOptions: { bar: { distributed: true, borderRadius: 4 } },
                                legend: { show: false },
                                dataLabels: { enabled: true },
                            }).render();
                        }
                    });
                </script>
            </div>
        @endif

        {{-- Filter --}}
        <div class="flex items-center gap-2 mb-4">
            @foreach(['all' => 'Alle', 'winners' => 'Gewinner', 'losers' => 'Verlierer'] as $type => $label)
                <button wire:click="setFilterType('{{ $type }}')"
                        class="px-3 py-1.5 text-sm rounded-lg {{ $filterType === $type ? 'bg-indigo-50 text-indigo-600 font-medium' : 'text-gray-500 hover:bg-gray-50' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Rankings Table --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left text-gray-400">
                        <th class="px-4 py-3">URL</th>
                        <th class="px-4 py-3">Keyword</th>
                        <th class="px-4 py-3 text-right">Position</th>
                        <th class="px-4 py-3 text-right">Veränderung</th>
                        <th class="px-4 py-3">SERP Features</th>
                        <th class="px-4 py-3 text-right">Datum</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rankings as $entry)
                        <tr wire:key="rank-{{ $entry->id }}" class="border-b border-gray-50 hover:bg-gray-50/50">
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
                                    <span class="{{ $entry->position_delta > 0 ? 'text-green-600' : ($entry->position_delta < 0 ? 'text-red-600' : 'text-gray-400') }} font-medium">
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
                            <td class="px-4 py-2.5 text-right text-xs text-gray-400">{{ $entry->tracked_at?->format('d.m.Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-gray-400">
                                Keine Ranking-Daten für diesen Zeitraum.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rankings->links() }}
        </div>

    </x-ui-page-container>
</x-ui-page>
