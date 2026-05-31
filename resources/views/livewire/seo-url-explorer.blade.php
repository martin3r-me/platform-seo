<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'URLs'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="$set('showAddModal', true)">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>URL hinzufügen</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'urls'])
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">

            {{-- Intro --}}
            <div>
                <p class="text-[13px] text-gray-500">Alle registrierten URLs deines Projekts. Jede URL wird regelmäßig gecrawlt, um Keywords, Rankings, Backlinks und On-Page-Faktoren zu erfassen. Eigene URLs (blau) werden mit Wettbewerbern (gelber Punkt) verglichen.</p>
            </div>

            {{-- Filters --}}
            <div class="flex items-center gap-3 flex-wrap">
                <div class="relative flex-1 min-w-[200px] max-w-sm">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                        @svg('heroicon-o-magnifying-glass', 'w-4 h-4 text-gray-400')
                    </div>
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="URL suchen..."
                           class="w-full border border-gray-200 rounded-lg pl-9 pr-3 py-2 text-[13px] bg-white focus:ring-2 focus:ring-indigo-200 focus:border-indigo-400 transition">
                </div>
                <select wire:model.live="filterIsOwn" class="border border-gray-200 rounded-lg px-3 py-2 text-[13px] bg-white">
                    <option value="">Alle URLs</option>
                    <option value="1">Eigene</option>
                    <option value="0">Wettbewerber</option>
                </select>
                <select wire:model.live="filterStatus" class="border border-gray-200 rounded-lg px-3 py-2 text-[13px] bg-white">
                    <option value="">Alle Status</option>
                    <option value="active">Aktiv</option>
                    <option value="redirect">Redirect</option>
                    <option value="error">Fehler</option>
                    <option value="pending">Ausstehend</option>
                </select>
                @if(!empty($selectedUrls))
                    <div class="flex items-center gap-2 ml-auto">
                        <x-ui-button variant="secondary" size="sm" wire:click="enrichSelected">
                            @svg('heroicon-o-arrow-path', 'w-4 h-4')
                            Enrichen ({{ count($selectedUrls) }})
                        </x-ui-button>
                        <x-ui-button variant="danger" size="sm" wire:click="deleteSelected" wire:confirm="Ausgewählte URLs löschen?">
                            @svg('heroicon-o-trash', 'w-4 h-4')
                            Löschen ({{ count($selectedUrls) }})
                        </x-ui-button>
                    </div>
                @endif
            </div>

            {{-- URL Table --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <table class="w-full text-[13px]">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                            <th class="px-4 py-2.5 w-8">
                                <input type="checkbox" wire:model.live="selectAll" class="rounded border-gray-300">
                            </th>
                            <th class="px-4 py-2.5 text-left cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('url')">
                                URL
                                @if($sortField === 'url') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            <th class="px-4 py-2.5 text-center">Status</th>
                            <th class="px-4 py-2.5 text-right">Kinder</th>
                            <th class="px-4 py-2.5 text-right cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('keyword_count')">
                                Keywords
                                @if($sortField === 'keyword_count') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            <th class="px-4 py-2.5 text-right cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('total_search_volume')">
                                SV
                                @if($sortField === 'total_search_volume') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            <th class="px-4 py-2.5 text-right cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('visibility_score')">
                                Sichtbarkeit
                                @if($sortField === 'visibility_score') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            <th class="px-4 py-2.5 text-right cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('backlink_count')">
                                Backlinks
                                @if($sortField === 'backlink_count') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                            <th class="px-4 py-2.5 text-right">On-Page</th>
                            <th class="px-4 py-2.5 text-right cursor-pointer hover:text-gray-700 select-none" wire:click="sortBy('last_crawled_at')">
                                Aktualisiert
                                @if($sortField === 'last_crawled_at') <span>{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($urls as $url)
                            <tr wire:key="url-{{ $url->id }}" class="hover:bg-gray-50 transition-colors">
                                <td class="px-4 py-2.5">
                                    <input type="checkbox" wire:model.live="selectedUrls" value="{{ $url->id }}" class="rounded border-gray-300">
                                </td>
                                <td class="px-4 py-2.5">
                                    <div class="flex items-center gap-2">
                                        @if(!$url->is_own)
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0" title="Wettbewerber"></span>
                                        @endif
                                        <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-xs font-medium">
                                            {{ ($url->path && $url->path !== '/') ? $url->path : $url->domain }}
                                        </a>
                                    </div>
                                    @if($url->path && $url->path !== '/')
                                        <div class="text-[10px] text-gray-400 ml-{{ $url->is_own ? '0' : '3.5' }}">{{ $url->domain }}</div>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-center">
                                    @include('seo::partials.url-status-badge', ['status' => $url->status, 'httpStatus' => $url->http_status])
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-500 tabular-nums">
                                    @if($url->child_count > 0)
                                        <span class="inline-flex items-center gap-0.5 text-[11px]">
                                            @svg('heroicon-o-document-duplicate', 'w-3 h-3 text-gray-400')
                                            {{ $url->child_count }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ $url->agg_keyword_count }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @include('seo::partials.sv-badge', ['volume' => $url->agg_search_volume])
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="font-semibold text-gray-900 tabular-nums">{{ number_format($url->agg_visibility, 1) }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ $url->agg_backlinks }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    @if($url->onPage && $url->onPage->overall_score !== null)
                                        @include('seo::partials.score-gauge', ['value' => $url->onPage->overall_score, 'label' => '', 'size' => 'sm'])
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right text-[11px] text-gray-400 tabular-nums">
                                    {{ $url->last_crawled_at?->format('d.m.Y') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-4 py-16 text-center">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                        @svg('heroicon-o-globe-alt', 'w-5 h-5 text-gray-400')
                                    </div>
                                    <p class="text-sm text-gray-500 font-medium mb-1">Noch keine URLs</p>
                                    <p class="text-xs text-gray-400">Füge URLs hinzu, um mit dem Tracking zu starten.</p>
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
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'urls'])
    </x-slot>

    {{-- Add URL Modal --}}
    <x-ui-modal wire:model="showAddModal" title="URLs hinzufügen">
        <form wire:submit="addUrls">
            <div class="space-y-4">
                <div>
                    <label class="block text-[13px] font-medium text-gray-700 mb-1">URLs (eine pro Zeile)</label>
                    <textarea wire:model="newUrls" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-[13px] font-mono"
                              placeholder="https://example.com/seite-1&#10;https://example.com/seite-2"></textarea>
                    <p class="text-[11px] text-gray-400 mt-1">Die URLs werden automatisch gecrawlt und Keywords, Rankings sowie Backlinks gesammelt.</p>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-[13px] text-gray-700">
                        <input type="checkbox" wire:model="newUrlsIsOwn" class="rounded">
                        <span>Eigene URLs (nicht Wettbewerber)</span>
                    </label>
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showAddModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">Hinzufügen</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>
</x-ui-page>
