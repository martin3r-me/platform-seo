<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="URL-Listen" icon="heroicon-o-queue-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'URL-Listen'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neue Liste</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.project-tabs', ['active' => 'lists'])

        <div class="flex gap-6">
            {{-- Sidebar: Lists --}}
            <div class="w-72 flex-shrink-0 space-y-2">
                @forelse($lists as $list)
                    <div wire:key="list-{{ $list->id }}"
                         wire:click="selectList({{ $list->id }})"
                         class="p-3 rounded-xl border cursor-pointer transition
                                {{ $activeListId === $list->id ? 'border-indigo-300 bg-indigo-50' : 'border-gray-100 bg-white hover:border-gray-200' }}">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-sm text-gray-900 truncate">{{ $list->name }}</span>
                            <span class="text-xs text-gray-400">{{ $list->urls_count }} URLs</span>
                        </div>
                        @if($list->description)
                            <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $list->description }}</p>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-gray-400 px-3 py-6 text-center">
                        Noch keine Listen vorhanden.
                    </div>
                @endforelse
            </div>

            {{-- Main: List detail --}}
            <div class="flex-1 min-w-0">
                @if($activeList)
                    {{-- List header --}}
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-gray-900">{{ $activeList->name }}</h2>
                            @if($activeList->description)
                                <p class="text-sm text-gray-500 mt-0.5">{{ $activeList->description }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui-button variant="secondary" size="sm" wire:click="openAddUrlsModal">
                                @svg('heroicon-o-plus', 'w-4 h-4')
                                <span>URLs hinzufügen</span>
                            </x-ui-button>
                            <x-ui-button variant="secondary" size="sm" wire:click="openEditModal({{ $activeList->id }})">
                                @svg('heroicon-o-pencil', 'w-4 h-4')
                            </x-ui-button>
                            <x-ui-button variant="danger" size="sm" wire:click="deleteList({{ $activeList->id }})" wire:confirm="Liste löschen? Die URLs selbst bleiben erhalten.">
                                @svg('heroicon-o-trash', 'w-4 h-4')
                            </x-ui-button>
                        </div>
                    </div>

                    {{-- Aggregated metrics --}}
                    <div class="grid grid-cols-4 gap-4 mb-6">
                        <div class="bg-white rounded-xl border border-gray-100 p-4">
                            <div class="text-xs text-gray-500">Sichtbarkeit</div>
                            <div class="text-lg font-semibold text-gray-900 mt-1">{{ number_format($aggregated['visibility_score'], 1) }}</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-4">
                            <div class="text-xs text-gray-500">Keywords</div>
                            <div class="text-lg font-semibold text-gray-900 mt-1">{{ number_format($aggregated['keyword_count']) }}</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-4">
                            <div class="text-xs text-gray-500">Suchvolumen</div>
                            <div class="text-lg font-semibold text-gray-900 mt-1">{{ number_format($aggregated['total_search_volume']) }}</div>
                        </div>
                        <div class="bg-white rounded-xl border border-gray-100 p-4">
                            <div class="text-xs text-gray-500">Backlinks</div>
                            <div class="text-lg font-semibold text-gray-900 mt-1">{{ number_format($aggregated['backlink_count']) }}</div>
                        </div>
                    </div>

                    {{-- URLs table --}}
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left">
                                    <th class="px-4 py-3">URL</th>
                                    <th class="px-4 py-3 text-right">Kinder</th>
                                    <th class="px-4 py-3 text-right">Sichtbarkeit</th>
                                    <th class="px-4 py-3 text-right">Keywords</th>
                                    <th class="px-4 py-3 text-right">SV</th>
                                    <th class="px-4 py-3 text-right">Backlinks</th>
                                    <th class="px-4 py-3 w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($listUrls as $url)
                                    <tr wire:key="lurl-{{ $url->id }}" class="border-b border-gray-50 hover:bg-gray-50/50">
                                        <td class="px-4 py-2.5">
                                            <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-sm">
                                                {{ $url->path ?: '/' }}
                                            </a>
                                            <span class="text-xs text-gray-400">{{ $url->domain }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $url->child_count }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            <span class="font-medium text-gray-900">{{ number_format($url->agg_visibility, 1) }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $url->agg_keywords }}</td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($url->agg_search_volume) }}</td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $url->agg_backlinks }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            <button wire:click="removeUrlFromList({{ $url->id }})" class="text-gray-400 hover:text-red-500 transition" title="Aus Liste entfernen">
                                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-4 py-12 text-center text-gray-400">
                                            Noch keine URLs in dieser Liste.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="flex items-center justify-center h-64 text-gray-400 text-sm">
                        Wähle eine Liste aus oder erstelle eine neue.
                    </div>
                @endif
            </div>
        </div>

    </x-ui-page-container>

    {{-- Create/Edit List Modal --}}
    <x-ui-modal wire:model="showListModal" title="{{ $editingListId ? 'Liste bearbeiten' : 'Neue Liste erstellen' }}">
        <form wire:submit="saveList">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" wire:model="listName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                           placeholder="z.B. BHG.GROUP" autofocus>
                    @error('listName') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung (optional)</label>
                    <textarea wire:model="listDescription" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"
                              placeholder="Wofür ist diese Liste?"></textarea>
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showListModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">{{ $editingListId ? 'Speichern' : 'Erstellen' }}</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>

    {{-- Add URLs Modal --}}
    <x-ui-modal wire:model="showAddUrlsModal" title="URLs zur Liste hinzufügen">
        <div class="space-y-4">
            <input type="text" wire:model.live.debounce.300ms="urlSearch" placeholder="URL suchen..."
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" autofocus>

            <div class="max-h-80 overflow-y-auto border border-gray-200 rounded-lg divide-y divide-gray-100">
                @forelse($availableUrls as $url)
                    <label wire:key="avail-{{ $url->id }}" class="flex items-center gap-3 px-3 py-2 hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" wire:model="selectedUrlIds" value="{{ $url->id }}" class="rounded">
                        <div class="min-w-0">
                            <div class="text-sm text-gray-900 truncate">{{ $url->path ?: '/' }}</div>
                            <div class="text-xs text-gray-400">{{ $url->domain }}</div>
                        </div>
                    </label>
                @empty
                    <div class="px-3 py-6 text-center text-gray-400 text-sm">
                        Keine verfügbaren Root-URLs gefunden.
                    </div>
                @endforelse
            </div>

            @if(count($selectedUrlIds) > 0)
                <p class="text-sm text-gray-500">{{ count($selectedUrlIds) }} URL(s) ausgewählt</p>
            @endif
        </div>
        <x-slot name="footer">
            <x-ui-button variant="secondary" size="sm" wire:click="$set('showAddUrlsModal', false)">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" size="sm" wire:click="addUrlsToList" :disabled="empty($selectedUrlIds)">Hinzufügen</x-ui-button>
        </x-slot>
    </x-ui-modal>
</x-ui-page>
