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

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($lists as $list)
                <div wire:key="list-{{ $list->id }}" class="bg-white rounded-xl border border-gray-100 hover:border-gray-200 transition overflow-hidden">
                    <a href="{{ route('seo.lists.show', $list) }}" wire:navigate class="block p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium text-sm text-gray-900 truncate">{{ $list->name }}</span>
                            <span class="text-xs text-gray-400">{{ $list->urls_count }} URLs</span>
                        </div>
                        @if($list->description)
                            <p class="text-xs text-gray-500 line-clamp-2">{{ $list->description }}</p>
                        @endif
                    </a>
                    <div class="px-4 pb-3 flex items-center gap-2">
                        <button wire:click="openEditModal({{ $list->id }})" class="text-xs text-gray-400 hover:text-gray-600 transition">
                            @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                        </button>
                        <button wire:click="deleteList({{ $list->id }})" wire:confirm="Liste löschen? Die URLs selbst bleiben erhalten." class="text-xs text-gray-400 hover:text-red-500 transition">
                            @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                        </button>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-sm text-gray-400 px-3 py-12 text-center">
                    Noch keine Listen vorhanden. Erstelle eine neue Liste, um URLs zu gruppieren.
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
    <x-slot name="activity">
        <x-ui-page-sidebar title="Aktivitäten" width="w-80" :defaultOpen="true" storeKey="activityOpen" side="right">
            <div class="p-4 space-y-4">
                <div class="text-[13px] text-gray-400">Letzte Änderungen</div>
            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
