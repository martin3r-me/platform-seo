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

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'lists'])
    </x-slot>

    <x-ui-page-container>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
            @forelse($lists as $list)
                <div wire:key="list-{{ $list->id }}" class="bg-white rounded-xl border border-gray-200 hover:border-indigo-200 hover:shadow-md transition-all group overflow-hidden">
                    <a href="{{ route('seo.lists.show', $list) }}" wire:navigate class="block p-5">
                        {{-- Header --}}
                        <div class="flex items-start justify-between mb-3">
                            <div class="min-w-0">
                                <h3 class="font-semibold text-[15px] text-gray-900 truncate group-hover:text-indigo-600 transition-colors">{{ $list->name }}</h3>
                                @if($list->description)
                                    <p class="text-[12px] text-gray-400 mt-0.5 line-clamp-1">{{ $list->description }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 ml-3 inline-flex items-center gap-1 text-[11px] font-medium text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">
                                {{ $list->urls_count }} URLs
                            </span>
                        </div>

                        {{-- Mini Stats Grid --}}
                        <div class="grid grid-cols-4 gap-2">
                            <div class="bg-gray-50 rounded-lg px-2.5 py-2 text-center">
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide">KWs</div>
                                <div class="text-[13px] font-semibold text-gray-800 tabular-nums mt-0.5">{{ number_format($list->agg_keywords) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-lg px-2.5 py-2 text-center">
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide">SV</div>
                                <div class="text-[13px] font-semibold text-gray-800 tabular-nums mt-0.5">
                                    {{ $list->agg_search_volume >= 1000 ? number_format($list->agg_search_volume / 1000, 1) . 'K' : number_format($list->agg_search_volume) }}
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-lg px-2.5 py-2 text-center">
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide">Sicht.</div>
                                <div class="text-[13px] font-semibold text-gray-800 tabular-nums mt-0.5">{{ number_format($list->agg_visibility, 0) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-lg px-2.5 py-2 text-center">
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide">Links</div>
                                <div class="text-[13px] font-semibold text-gray-800 tabular-nums mt-0.5">{{ number_format($list->agg_backlinks) }}</div>
                            </div>
                        </div>
                    </a>

                    {{-- Actions --}}
                    <div class="px-5 py-2.5 border-t border-gray-100 flex items-center gap-3 opacity-0 group-hover:opacity-100 transition-opacity">
                        <button wire:click="openEditModal({{ $list->id }})" class="text-[11px] text-gray-400 hover:text-indigo-600 transition flex items-center gap-1">
                            @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                            <span>Bearbeiten</span>
                        </button>
                        <button wire:click="deleteList({{ $list->id }})" wire:confirm="Liste löschen? Die URLs selbst bleiben erhalten." class="text-[11px] text-gray-400 hover:text-red-500 transition flex items-center gap-1">
                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                            <span>Löschen</span>
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16">
                    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        @svg('heroicon-o-queue-list', 'w-6 h-6 text-gray-400')
                    </div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Noch keine Listen</p>
                    <p class="text-xs text-gray-400">Erstelle eine neue Liste, um URLs zu gruppieren und gemeinsam zu analysieren.</p>
                </div>
            @endforelse
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
</x-ui-page>
