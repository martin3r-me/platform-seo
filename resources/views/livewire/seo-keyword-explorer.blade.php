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

    <x-ui-page-container>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">{{ session('error') }}</div>
        @endif

        {{-- Aggregate Stats --}}
        @php $stats = $this->aggregateStats; @endphp
        <x-ui-stats-grid :cols="4">
            <x-ui-dashboard-tile title="Keywords" :count="$stats->count ?? 0" icon="heroicon-o-key" variant="primary" />
            <x-ui-dashboard-tile title="Suchvolumen" :count="$stats->total_sv ?? 0" icon="heroicon-o-magnifying-glass" variant="info" />
            <div class="bg-white border border-gray-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-warning-5 flex items-center justify-center">
                    @svg('heroicon-o-signal', 'w-5 h-5 text-warning-60')
                </div>
                <div>
                    <div class="text-[11px] text-gray-500 uppercase tracking-wide">Avg KD</div>
                    <div class="text-xl font-semibold text-gray-900">{{ $stats->avg_kd ?? 0 }}</div>
                </div>
            </div>
            <div class="bg-white border border-gray-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-success-5 flex items-center justify-center">
                    @svg('heroicon-o-currency-euro', 'w-5 h-5 text-success-60')
                </div>
                <div>
                    <div class="text-[11px] text-gray-500 uppercase tracking-wide">Avg CPC</div>
                    <div class="text-xl font-semibold text-gray-900">{{ $stats->avg_cpc ?? '0.00' }} €</div>
                </div>
            </div>
        </x-ui-stats-grid>

        {{-- Filters --}}
        <div class="flex items-center gap-3 mb-6 flex-wrap">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Keywords suchen..."
                   class="border border-gray-200 rounded-lg px-3 py-2 text-sm w-64">
            <select wire:model.live="filterIntent" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Alle Intents</option>
                <option value="informational">Informational</option>
                <option value="transactional">Transactional</option>
                <option value="navigational">Navigational</option>
                <option value="commercial">Commercial</option>
            </select>
            <select wire:model.live="filterTopic" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Alle Topics</option>
                @foreach($this->topics as $topic)
                    <option value="{{ $topic }}">{{ $topic }}</option>
                @endforeach
            </select>
            <select wire:model.live="filterCluster" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
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
        </div>

        {{-- Keywords Table --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left">
                        <th class="px-4 py-3 w-8">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded">
                        </th>
                        <th class="px-4 py-3 cursor-pointer hover:text-gray-700" wire:click="sortBy('keyword')">
                            Keyword
                            @if($sortField === 'keyword') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('search_volume')">
                            SV
                            @if($sortField === 'search_volume') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('keyword_difficulty')">
                            KD
                            @if($sortField === 'keyword_difficulty') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('cpc_cents')">
                            CPC
                            @if($sortField === 'cpc_cents') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-center">Intent</th>
                        <th class="px-4 py-3 text-right">URLs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($keywords as $keyword)
                        <tr wire:key="kw-{{ $keyword->id }}"
                            class="border-b border-gray-50 hover:bg-gray-50/50 cursor-pointer {{ $selectedKeywordId === $keyword->id ? 'bg-indigo-50' : '' }}"
                            wire:click="selectKeyword({{ $keyword->id }})">
                            <td class="px-4 py-2.5" wire:click.stop>
                                <input type="checkbox" wire:model.live="selectedKeywords" value="{{ $keyword->id }}" class="rounded">
                            </td>
                            <td class="px-4 py-2.5">
                                <div>
                                    <span class="font-medium text-gray-900">{{ $keyword->keyword }}</span>
                                    @if($keyword->cluster || $keyword->topic)
                                        <div class="flex items-center gap-2 mt-0.5">
                                            @if($keyword->cluster)
                                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-500">
                                                    @if($keyword->cluster->color)
                                                        <span class="w-1.5 h-1.5 rounded-full" style="background-color: {{ $keyword->cluster->color }}"></span>
                                                    @endif
                                                    {{ $keyword->cluster->name }}
                                                </span>
                                            @endif
                                            @if($keyword->topic)
                                                <span class="text-[10px] text-gray-400">{{ $keyword->topic }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @include('seo::partials.sv-badge', ['volume' => $keyword->search_volume])
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @include('seo::partials.kd-badge', ['value' => $keyword->keyword_difficulty])
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $keyword->cpc_euro !== null ? number_format($keyword->cpc_euro, 2) . ' €' : '—' }}</td>
                            <td class="px-4 py-2.5 text-center">
                                @if($keyword->search_intent)
                                    @php
                                        $intentColors = [
                                            'informational' => 'bg-blue-100 text-blue-700',
                                            'transactional' => 'bg-green-100 text-green-700',
                                            'navigational' => 'bg-purple-100 text-purple-700',
                                            'commercial' => 'bg-amber-100 text-amber-700',
                                        ];
                                        $intentLetters = [
                                            'informational' => 'I',
                                            'transactional' => 'T',
                                            'navigational' => 'N',
                                            'commercial' => 'C',
                                        ];
                                    @endphp
                                    <span class="inline-block w-6 h-6 leading-6 rounded-full text-[10px] font-bold text-center {{ $intentColors[$keyword->search_intent] ?? 'bg-gray-100 text-gray-600' }}" title="{{ ucfirst($keyword->search_intent) }}">
                                        {{ $intentLetters[$keyword->search_intent] ?? '?' }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $keyword->urls_count }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                Noch keine Keywords. Füge welche hinzu, um zu starten.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $keywords->links() }}
        </div>

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

    <x-slot name="activity">
        <x-ui-page-sidebar title="{{ $this->selectedKeyword?->keyword ?? 'Keyword Details' }}" width="w-[480px]" :defaultOpen="true" storeKey="kwDetailOpen" side="right">
            @if($this->selectedKeyword)
                @include('seo::partials.keyword-detail-panel', [
                    'keyword' => $this->selectedKeyword,
                    'urls' => $this->selectedKeywordUrls,
                    'positionHistory' => $this->selectedKeywordHistory,
                ])
            @else
                <div class="p-8 text-center text-gray-400">
                    <div class="mb-2">@svg('heroicon-o-cursor-arrow-rays', 'w-8 h-8 mx-auto text-gray-300')</div>
                    <p class="text-sm">Keyword auswählen um Details zu sehen</p>
                </div>
            @endif
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
