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
        @livewire('seo.sidebar', ['active' => 'dashboard'])
    </x-slot>

    <x-ui-page-container>

        {{-- Stats Grid --}}
        <x-ui-stats-grid :cols="6">
            <x-ui-dashboard-tile title="URLs" :count="$urlCounts['total']" icon="globe-alt" variant="primary" />
            <x-ui-dashboard-tile title="Eigene URLs" :count="$urlCounts['own']" icon="home" variant="info" />
            <x-ui-dashboard-tile title="Wettbewerber" :count="$urlCounts['competitor']" icon="user-group" variant="warning" />
            <x-ui-dashboard-tile title="Keywords" :count="$keywordCount" icon="key" variant="neutral" />
            <x-ui-dashboard-tile title="Sichtbarkeit" :count="$visibility['percentage']" icon="eye" variant="success" description="%" />
            <x-ui-dashboard-tile title="Budget" :count="$budgetSummary['percentage'] ?? 0" icon="banknotes" variant="{{ ($budgetSummary['percentage'] ?? 0) > 80 ? 'danger' : 'neutral' }}" description="{{ $budgetSummary['percentage'] !== null ? '%' : '—' }}" />
        </x-ui-stats-grid>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Positions-Verteilung --}}
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h3 class="text-sm font-medium text-gray-700 mb-4">Positions-Verteilung</h3>
                @php
                    $distChartId = 'pos-dist-' . uniqid();
                    $distData = json_encode(array_values($positionDistribution));
                    $distLabels = json_encode(array_keys($positionDistribution));
                @endphp
                <div id="{{ $distChartId }}" style="height: 250px;" wire:ignore></div>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        if (typeof ApexCharts !== 'undefined') {
                            new ApexCharts(document.querySelector('#{{ $distChartId }}'), {
                                chart: { type: 'bar', height: 250, toolbar: { show: false } },
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

            {{-- Sichtbarkeits-Verlauf --}}
            <div class="bg-white rounded-xl border border-gray-100 p-6">
                <h3 class="text-sm font-medium text-gray-700 mb-4">Sichtbarkeits-Verlauf (30 Tage)</h3>
                @if(!empty($visibilityHistory))
                    @include('seo::partials.sparkline', ['data' => $visibilityHistory, 'color' => '#6366f1', 'height' => 250])
                @else
                    <div class="flex items-center justify-center h-[250px] text-gray-400 text-sm">
                        Noch keine Snapshot-Daten vorhanden.
                    </div>
                @endif
            </div>
        </div>

        {{-- Top URLs --}}
        @if($topUrls->isNotEmpty())
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-medium text-gray-700">Top URLs nach Sichtbarkeit</h3>
                    <a href="{{ route('seo.urls') }}" wire:navigate class="text-xs text-indigo-600 hover:underline">Alle URLs</a>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-400">
                            <th class="px-6 py-3">URL</th>
                            <th class="px-4 py-3 text-right">Keywords</th>
                            <th class="px-4 py-3 text-right">SV</th>
                            <th class="px-4 py-3 text-right">Sichtbarkeit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topUrls as $url)
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50">
                                <td class="px-6 py-3">
                                    <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-md">
                                        {{ $url->path ?: '/' }}
                                    </a>
                                    <span class="text-xs text-gray-400">{{ $url->domain }}</span>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ $url->keyword_count }}</td>
                                <td class="px-4 py-3 text-right">
                                    @include('seo::partials.sv-badge', ['volume' => $url->total_search_volume])
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <span class="font-medium text-gray-900">{{ number_format($url->visibility_score, 1) }}</span>
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
                    <h3 class="text-sm font-medium text-gray-700">Aktuelle Signale</h3>
                    <a href="{{ route('seo.signals') }}" wire:navigate class="text-xs text-indigo-600 hover:underline">Alle anzeigen</a>
                </div>
                <div class="space-y-2">
                    @foreach($recentSignals as $signal)
                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-100">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0
                                @if($signal->severity === 'critical') bg-red-500
                                @elseif($signal->severity === 'warning') bg-amber-500
                                @elseif($signal->severity === 'watch') bg-blue-500
                                @else bg-gray-400
                                @endif"></span>
                            <div class="min-w-0 flex-1">
                                <span class="text-sm text-gray-700 truncate block">{{ $signal->title }}</span>
                                <div class="flex items-center gap-2 text-xs text-gray-400 mt-0.5">
                                    <span>{{ $signal->detected_at->format('d.m.Y') }}</span>
                                    @if($signal->url)
                                        <a href="{{ route('seo.urls.show', $signal->url) }}" wire:navigate class="text-indigo-500 hover:underline truncate">{{ $signal->url->path }}</a>
                                    @endif
                                </div>
                            </div>
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 rounded text-gray-500">{{ str_replace('_', ' ', $signal->signal_type) }}</span>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" wire:model="editName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @error('editName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                    <textarea wire:model="editDescription" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                    <input type="text" wire:model="editDomain" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showSettingsModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">Speichern</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>
</x-ui-page>
