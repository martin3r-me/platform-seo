<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Keywords" icon="heroicon-o-key" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.projects.index'],
            ['label' => $seoProject->name, 'route' => 'seo.projects.show', 'routeParams' => [$seoProject]],
            ['label' => 'Keywords'],
        ]">
            <x-ui-button variant="secondary" size="sm" wire:click="fetchMetrics">
                @svg('heroicon-o-arrow-path', 'w-4 h-4')
                <span>Metriken abrufen</span>
            </x-ui-button>
            <x-ui-button variant="primary" size="sm" wire:click="$set('showAddModal', true)">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Keywords hinzuf&uuml;gen</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>

        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-1 border-b border-gray-100 mb-6">
            <a href="{{ route('seo.projects.show', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Dashboard</a>
            <a href="{{ route('seo.projects.keywords', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600">Keywords</a>
            <a href="{{ route('seo.projects.rankings', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Rankings</a>
            <a href="{{ route('seo.projects.competitors', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Wettbewerber</a>
            <a href="{{ route('seo.projects.signals', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Signale</a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 bg-green-50 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="mb-4 p-3 bg-red-50 text-red-700 text-sm rounded-lg">{{ session('error') }}</div>
        @endif

        {{-- Filters --}}
        <div class="flex items-center gap-3 mb-6">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Keywords suchen..."
                   class="border border-gray-200 rounded-lg px-3 py-2 text-sm w-64">
            <select wire:model.live="filterIntent" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Alle Intents</option>
                <option value="informational">Informational</option>
                <option value="transactional">Transactional</option>
                <option value="navigational">Navigational</option>
                <option value="commercial">Commercial</option>
            </select>
            <select wire:model.live="filterCluster" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Alle Cluster</option>
                <option value="0">Ohne Cluster</option>
                @foreach($this->clusters as $cluster)
                    <option value="{{ $cluster->id }}">{{ $cluster->name }}</option>
                @endforeach
            </select>
            @if(!empty($selectedKeywords))
                <x-ui-button variant="danger" size="sm" wire:click="deleteSelected" wire:confirm="Ausgew&auml;hlte Keywords l&ouml;schen?">
                    {{ count($selectedKeywords) }} l&ouml;schen
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
                            @if($sortField === 'keyword')
                                <span class="text-xs">{{ $sortDirection === 'asc' ? '&uarr;' : '&darr;' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('search_volume')">
                            SV
                            @if($sortField === 'search_volume')
                                <span class="text-xs">{{ $sortDirection === 'asc' ? '&uarr;' : '&darr;' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('keyword_difficulty')">
                            KD
                            @if($sortField === 'keyword_difficulty')
                                <span class="text-xs">{{ $sortDirection === 'asc' ? '&uarr;' : '&darr;' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('cpc_cents')">
                            CPC
                            @if($sortField === 'cpc_cents')
                                <span class="text-xs">{{ $sortDirection === 'asc' ? '&uarr;' : '&darr;' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('position')">
                            Pos.
                            @if($sortField === 'position')
                                <span class="text-xs">{{ $sortDirection === 'asc' ? '&uarr;' : '&darr;' }}</span>
                            @endif
                        </th>
                        <th class="px-4 py-3">Intent</th>
                        <th class="px-4 py-3">Cluster</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($keywords as $keyword)
                        <tr wire:key="kw-{{ $keyword->id }}" class="border-b border-gray-50 hover:bg-gray-50/50">
                            <td class="px-4 py-2.5">
                                <input type="checkbox" wire:model.live="selectedKeywords" value="{{ $keyword->id }}" class="rounded">
                            </td>
                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ $keyword->keyword }}</td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $keyword->search_volume !== null ? number_format($keyword->search_volume) : '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @if($keyword->keyword_difficulty !== null)
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs
                                        @if($keyword->keyword_difficulty < 30) bg-green-100 text-green-700
                                        @elseif($keyword->keyword_difficulty < 60) bg-amber-100 text-amber-700
                                        @else bg-red-100 text-red-700
                                        @endif">{{ $keyword->keyword_difficulty }}</span>
                                @else
                                    <span class="text-gray-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $keyword->cpc_euro !== null ? number_format($keyword->cpc_euro, 2) . ' €' : '—' }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @if($keyword->position !== null)
                                    <span class="font-medium {{ $keyword->position <= 3 ? 'text-green-600' : ($keyword->position <= 10 ? 'text-blue-600' : 'text-gray-500') }}">{{ $keyword->position }}</span>
                                @else
                                    <span class="text-gray-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($keyword->search_intent)
                                    <span class="text-xs text-gray-500">{{ ucfirst($keyword->search_intent) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5">
                                @if($keyword->cluster)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-600">
                                        @if($keyword->cluster->color)
                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $keyword->cluster->color }}"></span>
                                        @endif
                                        {{ $keyword->cluster->name }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-gray-400">
                                Noch keine Keywords. F&uuml;ge welche hinzu, um zu starten.
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
    <x-ui-modal wire:model="showAddModal" title="Keywords hinzuf&uuml;gen">
        <form wire:submit="addKeywords">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Keywords (eins pro Zeile)</label>
                <textarea wire:model="newKeywords" rows="10" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                          placeholder="keyword 1&#10;keyword 2&#10;keyword 3"></textarea>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showAddModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">Hinzuf&uuml;gen</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>
</x-ui-page>
