<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Keywords" icon="heroicon-o-key" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
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
        @include('seo::partials.sidebar', ['active' => 'keywords'])
    </x-slot>

    <x-ui-page-container>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">{{ session('error') }}</div>
        @endif

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
                        <th class="px-4 py-3">Intent</th>
                        <th class="px-4 py-3 text-right">URLs</th>
                        <th class="px-4 py-3">Topic</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($keywords as $keyword)
                        <tr wire:key="kw-{{ $keyword->id }}" class="border-b border-gray-50 hover:bg-gray-50/50 cursor-pointer" wire:click="toggleExpand({{ $keyword->id }})">
                            <td class="px-4 py-2.5" wire:click.stop>
                                <input type="checkbox" wire:model.live="selectedKeywords" value="{{ $keyword->id }}" class="rounded">
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <svg class="w-3 h-3 text-gray-300 transition-transform {{ $expandedKeywordId === $keyword->id ? 'rotate-90' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                    <span class="font-medium text-gray-900">{{ $keyword->keyword }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @include('seo::partials.sv-badge', ['volume' => $keyword->search_volume])
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @include('seo::partials.kd-badge', ['value' => $keyword->keyword_difficulty])
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $keyword->cpc_euro !== null ? number_format($keyword->cpc_euro, 2) . ' €' : '—' }}</td>
                            <td class="px-4 py-2.5">
                                @if($keyword->search_intent)
                                    @php
                                        $intentColors = [
                                            'informational' => 'bg-blue-100 text-blue-700',
                                            'transactional' => 'bg-green-100 text-green-700',
                                            'navigational' => 'bg-purple-100 text-purple-700',
                                            'commercial' => 'bg-amber-100 text-amber-700',
                                        ];
                                    @endphp
                                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-medium {{ $intentColors[$keyword->search_intent] ?? 'bg-gray-100 text-gray-600' }}">
                                        {{ ucfirst($keyword->search_intent) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $keyword->urls_count }}</td>
                            <td class="px-4 py-2.5">
                                @if($keyword->topic)
                                    <span class="text-xs text-gray-500">{{ $keyword->topic }}</span>
                                @endif
                                @if($keyword->cluster)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500 ml-1">
                                        @if($keyword->cluster->color)
                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $keyword->cluster->color }}"></span>
                                        @endif
                                        {{ $keyword->cluster->name }}
                                    </span>
                                @endif
                            </td>
                        </tr>

                        {{-- Inline Expand: URLs ranking for this keyword --}}
                        @if($expandedKeywordId === $keyword->id)
                            <tr wire:key="kw-expand-{{ $keyword->id }}" class="bg-indigo-50/30">
                                <td colspan="8" class="px-8 py-3">
                                    @if($this->expandedUrls->isNotEmpty())
                                        <div class="text-xs text-gray-500 mb-2">URLs die für dieses Keyword ranken:</div>
                                        <div class="space-y-1">
                                            @foreach($this->expandedUrls as $url)
                                                <div class="flex items-center gap-3">
                                                    <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline text-sm truncate">{{ $url->path ?: '/' }}</a>
                                                    <span class="text-xs text-gray-400">{{ $url->domain }}</span>
                                                    @include('seo::partials.position-badge', ['position' => $url->keywords->first()?->pivot->position, 'change' => null])
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-xs text-gray-400">Keine URLs ranken für dieses Keyword.</div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-400">
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
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-[13px] text-gray-400">Letzte Änderungen</div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
