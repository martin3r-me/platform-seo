<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Keywords" icon="heroicon-o-key" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'URLs', 'route' => 'seo.urls'],
            ['label' => ($seoUrl->path && $seoUrl->path !== '/') ? Str::limit($seoUrl->path, 20) : $seoUrl->domain, 'href' => route('seo.urls.show', $seoUrl)],
            ['label' => 'Keywords'],
        ]">
            <x-ui-button variant="secondary" size="sm" wire:click="fetchMetrics">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                <span>Metriken abrufen</span>
            </x-ui-button>
            <x-ui-button variant="primary" size="sm" wire:click="$set('showAddModal', true)">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Keywords hinzufügen</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'urls'])
    </x-slot>

    <x-ui-page-container padding="px-0 pb-0" spacing="" background="bg-white">

        @if(session('success'))
            <div class="mx-5 mt-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mx-5 mt-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">{{ session('error') }}</div>
        @endif

        {{-- Search & Filters Bar --}}
        @php $stats = $this->aggregateStats; @endphp
        <div class="border-b border-gray-100 bg-gray-50/60 px-5 py-3">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        @svg('heroicon-o-magnifying-glass', 'w-4 h-4 text-gray-400')
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Keywords suchen..."
                           class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-sm bg-white focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
                </div>
                <select wire:model.live="filterIntent" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Alle Intents</option>
                    <option value="informational">Informational</option>
                    <option value="transactional">Transactional</option>
                    <option value="navigational">Navigational</option>
                    <option value="commercial">Commercial</option>
                </select>
                <select wire:model.live="filterTopic" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Alle Topics</option>
                    @foreach($this->topics as $topic)
                        <option value="{{ $topic }}">{{ $topic }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterCluster" class="border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Alle Cluster</option>
                    <option value="0">Ohne Cluster</option>
                    @foreach($this->clusters as $cluster)
                        <option value="{{ $cluster->id }}">{{ $cluster->name }}</option>
                    @endforeach
                </select>
                @if(!empty($selectedKeywords))
                    <x-ui-button variant="danger" size="sm" wire:click="deleteSelected" wire:confirm="Ausgewählte Keywords löschen?">
                        {{ count($selectedKeywords) }} löschen
                    </x-ui-button>
                @endif

                {{-- Inline aggregate stats --}}
                <div class="ml-auto flex items-center gap-4 text-[11px] text-gray-500">
                    <span><b class="text-gray-700">{{ number_format($stats->count ?? 0) }}</b> Keywords</span>
                    <span>SV <b class="text-gray-700">{{ number_format($stats->total_sv ?? 0) }}</b></span>
                    <span>Avg KD <b class="text-gray-700">{{ $stats->avg_kd ?? 0 }}</b></span>
                    <span>Avg CPC <b class="text-gray-700">{{ number_format($stats->avg_cpc ?? 0, 2) }} €</b></span>
                </div>
            </div>
        </div>

        {{-- Keywords Table --}}
        <div class="overflow-y-auto flex-1" style="max-height: calc(100vh - 180px);">
            <table class="w-full text-sm">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                        <th class="px-3 py-2.5 w-8">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300">
                        </th>
                        <th class="px-3 py-2.5 text-left cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('keyword')">
                            Keyword
                            @if($sortField === 'keyword') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-3 py-2.5 w-[72px] text-center">Trend</th>
                        <th class="px-3 py-2.5 text-right cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('search_volume')">
                            Search
                            @if($sortField === 'search_volume') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-3 py-2.5 text-right cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('cpc_cents')">
                            CPC
                            @if($sortField === 'cpc_cents') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-3 py-2.5 text-right cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('keyword_difficulty')">
                            KD
                            @if($sortField === 'keyword_difficulty') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($keywords as $keyword)
                        <tr wire:key="kw-{{ $keyword->id }}"
                            class="border-b border-gray-50 cursor-pointer transition-colors
                                   {{ $selectedKeywordId === $keyword->id ? 'bg-indigo-50 hover:bg-indigo-50' : 'hover:bg-gray-50/80' }}"
                            wire:click="selectKeyword({{ $keyword->id }})">
                            <td class="px-3 py-2" wire:click.stop>
                                <input type="checkbox" wire:model.live="selectedKeywords" value="{{ $keyword->id }}" class="rounded border-gray-300">
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900 text-[13px]">{{ $keyword->keyword }}</div>
                                @if($keyword->cluster || $keyword->topic)
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        @if($keyword->cluster)
                                            <span class="inline-flex items-center gap-1 text-[10px] text-gray-400">
                                                @if($keyword->cluster->color)
                                                    <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background-color: {{ $keyword->cluster->color }}"></span>
                                                @endif
                                                {{ $keyword->cluster->name }}
                                            </span>
                                        @endif
                                        @if($keyword->topic)
                                            <span class="text-[10px] text-gray-300">·</span>
                                            <span class="text-[10px] text-gray-400">{{ $keyword->topic }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>
                            <td class="px-1 py-2" wire:click.stop>
                                @if($keyword->monthly_volumes && count($keyword->monthly_volumes) >= 6)
                                    <div wire:key="trend-{{ $keyword->id }}" wire:ignore
                                         x-data x-init="$nextTick(() => {
                                            if (typeof ApexCharts !== 'undefined') {
                                                new ApexCharts($el, {
                                                    chart: { type: 'bar', height: 28, sparkline: { enabled: true } },
                                                    series: [{ data: {{ json_encode(array_values($keyword->monthly_volumes)) }} }],
                                                    colors: ['#c7d2fe'],
                                                    plotOptions: { bar: { borderRadius: 1, columnWidth: '60%' } },
                                                    tooltip: { enabled: false }
                                                }).render();
                                            }
                                        })"
                                         style="height: 28px; width: 60px;">
                                    </div>
                                @else
                                    <div class="w-[60px] h-[28px]"></div>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-medium text-gray-800 text-[13px]">
                                {{ $keyword->search_volume !== null ? number_format($keyword->search_volume) : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right text-gray-500 text-[12px]">
                                {{ $keyword->cpc_euro !== null ? number_format($keyword->cpc_euro, 2) . ' €' : '—' }}
                            </td>
                            <td class="px-3 py-2 text-right">
                                @include('seo::partials.kd-badge', ['value' => $keyword->keyword_difficulty])
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-16 text-center text-gray-400">
                                <div class="mb-2">@svg('heroicon-o-key', 'w-8 h-8 mx-auto text-gray-300')</div>
                                <p class="text-sm">Noch keine Keywords. Füge welche hinzu, um zu starten.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($hasMore)
            <div x-data x-intersect="$wire.loadMore()" class="py-4 text-center">
                <span wire:loading.delay wire:target="loadMore" class="text-[12px] text-gray-400">Laden...</span>
            </div>
        @endif

    </x-ui-page-container>

    {{-- Add Keywords Modal --}}
    <x-ui-modal wire:model="showAddModal" title="Keywords hinzufügen">
        <form wire:submit="addKeywords">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Keywords (eins pro Zeile)</label>
                <textarea wire:model="newKeywords" rows="10" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                          placeholder="keyword 1&#10;keyword 2&#10;keyword 3"></textarea>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showAddModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">Hinzufügen</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>

    {{-- Right Detail Panel (KWFinder-style) --}}
    <x-slot name="activity">
        <x-ui-page-sidebar title="{{ $this->selectedKeyword?->keyword ?? 'Keyword Details' }}" width="w-[440px]" :defaultOpen="true" storeKey="kwDetailOpen" side="right">
            @if($this->selectedKeyword)
                @include('seo::partials.keyword-detail-panel', [
                    'keyword' => $this->selectedKeyword,
                    'urls' => $this->selectedKeywordUrls,
                    'positionHistory' => $this->selectedKeywordHistory,
                ])
            @else
                <div class="flex flex-col items-center justify-center h-full py-20 text-center px-8">
                    <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mb-4">
                        @svg('heroicon-o-cursor-arrow-rays', 'w-7 h-7 text-indigo-300')
                    </div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Kein Keyword ausgewählt</p>
                    <p class="text-xs text-gray-400">Klicke auf ein Keyword in der Liste, um SERP-Daten, Suchvolumen-Trends und Schwierigkeitsgrad zu sehen.</p>
                </div>
            @endif
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
