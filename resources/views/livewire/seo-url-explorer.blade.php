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
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.help-banner', ['lens' => 'urls'])
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
                <button wire:click="$toggle('groupByContext')"
                        class="inline-flex items-center gap-1.5 border rounded-lg px-3 py-2 text-[13px] transition-colors {{ $groupByContext ? 'bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50' }}"
                        title="URLs nach Organisations-Kontext gruppieren">
                    @svg('heroicon-o-rectangle-group', 'w-4 h-4')
                    <span>Nach Kontext</span>
                </button>
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
                        @if($urls->isEmpty())
                            <tr>
                                <td colspan="10" class="px-4 py-16 text-center">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                        @svg('heroicon-o-globe-alt', 'w-5 h-5 text-gray-400')
                                    </div>
                                    <p class="text-sm text-gray-500 font-medium mb-1">Noch keine URLs</p>
                                    <p class="text-xs text-gray-400">Füge URLs hinzu, um mit dem Tracking zu starten.</p>
                                </td>
                            </tr>
                        @elseif($grouped !== null)
                            @foreach($grouped as $group)
                                <tr wire:key="group-{{ $group['entityId'] ?? 'none' }}" class="bg-gray-50/80 border-y border-gray-100">
                                    <td colspan="10" class="px-4 py-2">
                                        <div class="flex items-center gap-2 text-[12px]">
                                            @svg('heroicon-o-rectangle-group', 'w-3.5 h-3.5 text-gray-400')
                                            @if($group['entityId'])
                                                <a href="{{ route('seo.context', $group['entityId']) }}" wire:navigate class="font-semibold text-gray-700 hover:text-indigo-600">{{ $group['label'] }}</a>
                                            @else
                                                <span class="font-semibold text-gray-500">{{ $group['label'] }}</span>
                                            @endif
                                            <span class="text-[11px] text-gray-400 tabular-nums">· {{ $group['urls']->count() }} URL(s)</span>
                                        </div>
                                    </td>
                                </tr>
                                @foreach($group['urls'] as $url)
                                    @include('seo::partials.url-row', ['url' => $url])
                                @endforeach
                            @endforeach
                        @else
                            @foreach($urls as $url)
                                @include('seo::partials.url-row', ['url' => $url])
                            @endforeach
                        @endif
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
