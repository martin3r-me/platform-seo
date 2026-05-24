@include('seo::partials.seo-colors')

<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="URLs" icon="heroicon-o-globe-alt" />
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
        @livewire('seo.sidebar', ['active' => 'urls'])
    </x-slot>

    <x-ui-page-container>

        {{-- Filters --}}
        <div class="flex items-center gap-3 mb-6 flex-wrap">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="URL suchen..."
                   class="border border-gray-200 rounded-lg px-3 py-2 text-sm w-64">
            <select wire:model.live="filterIsOwn" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                <option value="">Alle URLs</option>
                <option value="1">Eigene</option>
                <option value="0">Wettbewerber</option>
            </select>
            <select wire:model.live="filterStatus" class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
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
                        <span>Enrichen ({{ count($selectedUrls) }})</span>
                    </x-ui-button>
                    <x-ui-button variant="danger" size="sm" wire:click="deleteSelected" wire:confirm="Ausgewählte URLs löschen?">
                        @svg('heroicon-o-trash', 'w-4 h-4')
                        <span>Löschen ({{ count($selectedUrls) }})</span>
                    </x-ui-button>
                </div>
            @endif
        </div>

        {{-- URL Table --}}
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 text-left">
                        <th class="px-4 py-3 w-8">
                            <input type="checkbox" wire:model.live="selectAll" class="rounded">
                        </th>
                        <th class="px-4 py-3 cursor-pointer hover:text-gray-700" wire:click="sortBy('url')">
                            URL
                            @if($sortField === 'url') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Kinder</th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('keyword_count')">
                            Keywords
                            @if($sortField === 'keyword_count') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('total_search_volume')">
                            SV
                            @if($sortField === 'total_search_volume') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('visibility_score')">
                            Sichtbarkeit
                            @if($sortField === 'visibility_score') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('backlink_count')">
                            Backlinks
                            @if($sortField === 'backlink_count') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                        <th class="px-4 py-3 text-right">On-Page</th>
                        <th class="px-4 py-3 text-right cursor-pointer hover:text-gray-700" wire:click="sortBy('last_crawled_at')">
                            Aktualisiert
                            @if($sortField === 'last_crawled_at') <span class="text-xs">{{ $sortDirection === 'asc' ? '↑' : '↓' }}</span> @endif
                        </th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($urls as $url)
                        <tr wire:key="url-{{ $url->id }}" class="border-b border-gray-50 hover:bg-gray-50/50">
                            <td class="px-4 py-2.5">
                                <input type="checkbox" wire:model.live="selectedUrls" value="{{ $url->id }}" class="rounded">
                            </td>
                            <td class="px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    @if(!$url->is_own)
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0" title="Wettbewerber"></span>
                                    @endif
                                    <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-xs">
                                        {{ $url->path ?: '/' }}
                                    </a>
                                </div>
                                <span class="text-xs text-gray-400">{{ $url->domain }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-center">
                                @include('seo::partials.url-status-badge', ['status' => $url->status, 'httpStatus' => $url->http_status])
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">
                                @if($url->child_count > 0)
                                    <span class="inline-flex items-center gap-1 text-xs text-gray-500">
                                        @svg('heroicon-o-document-duplicate', 'w-3.5 h-3.5')
                                        {{ $url->child_count }}
                                    </span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $url->agg_keyword_count }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @include('seo::partials.sv-badge', ['volume' => $url->agg_search_volume])
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <span class="font-medium text-gray-900">{{ number_format($url->agg_visibility, 1) }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $url->agg_backlinks }}</td>
                            <td class="px-4 py-2.5 text-right">
                                @if($url->onPage && $url->onPage->overall_score !== null)
                                    @include('seo::partials.score-gauge', ['value' => $url->onPage->overall_score, 'label' => '', 'size' => 'sm'])
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2.5 text-right text-xs text-gray-400">
                                {{ $url->last_crawled_at?->format('d.m.Y') ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-4 py-12 text-center text-gray-400">
                                Noch keine URLs. Füge welche hinzu, um zu starten.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $urls->links() }}
        </div>

    </x-ui-page-container>

    {{-- Add URL Modal --}}
    <x-ui-modal wire:model="showAddModal" title="URLs hinzufügen">
        <form wire:submit="addUrls">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">URLs (eine pro Zeile)</label>
                    <textarea wire:model="newUrls" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono"
                              placeholder="https://example.com/seite-1&#10;https://example.com/seite-2"></textarea>
                </div>
                <div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
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
