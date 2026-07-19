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
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.help-banner', ['lens' => 'lists'])

        {{-- Intro --}}
        <p class="text-[13px] text-gray-500 mb-6">URL-Listen gruppieren Seiten thematisch oder nach Projekt. Jede Liste trackt Keywords, Rankings und Wettbewerber unabhaengig. So behältst du den Überblick über verschiedene Bereiche deiner Website.</p>

        {{-- Global Summary --}}
        @if($lists->isNotEmpty())
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-queue-list', 'w-4 h-4 text-indigo-500')
                        <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Listen</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $lists->count() }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-globe-alt', 'w-4 h-4 text-blue-500')
                        <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">URLs gesamt</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $lists->sum('urls_count') }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-key', 'w-4 h-4 text-amber-500')
                        <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Keywords gesamt</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($lists->sum('agg_keywords')) }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center gap-2 mb-1">
                        @svg('heroicon-o-eye', 'w-4 h-4 text-green-500')
                        <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Sichtbarkeit</span>
                    </div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($lists->sum('agg_visibility'), 0) }}</div>
                </div>
            </div>
        @endif

        {{-- List Cards --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            @forelse($lists as $list)
                <div wire:key="list-{{ $list->id }}" class="bg-white rounded-xl border border-gray-200 hover:border-indigo-300 hover:shadow-lg transition-all group overflow-hidden">
                    {{-- Card Header --}}
                    <a href="{{ route('seo.lists.show', $list) }}" wire:navigate class="block">
                        <div class="px-5 pt-5 pb-3">
                            <div class="flex items-start justify-between mb-1">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-[16px] text-gray-900 truncate group-hover:text-indigo-600 transition-colors">{{ $list->name }}</h3>
                                    @if($list->description)
                                        <p class="text-[12px] text-gray-400 mt-0.5 line-clamp-2">{{ $list->description }}</p>
                                    @endif
                                </div>
                                {{-- Signal badges --}}
                                <div class="flex items-center gap-1.5 ml-3 shrink-0">
                                    @if($list->agg_signals_critical > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-red-100 text-red-700">
                                            @svg('heroicon-s-exclamation-triangle', 'w-3 h-3')
                                            {{ $list->agg_signals_critical }}
                                        </span>
                                    @endif
                                    @if($list->agg_signals_warning > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">
                                            {{ $list->agg_signals_warning }}
                                        </span>
                                    @endif
                                    @if($list->agg_signals_opportunity > 0)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                                            {{ $list->agg_signals_opportunity }}
                                        </span>
                                    @endif
                                </div>
                            </div>

                            {{-- URL count + type breakdown --}}
                            <div class="flex items-center gap-3 mt-2 text-[11px] text-gray-400">
                                <span class="flex items-center gap-1">
                                    @svg('heroicon-o-globe-alt', 'w-3.5 h-3.5')
                                    {{ $list->urls_count }} URLs
                                </span>
                                @if($list->agg_own_count > 0)
                                    <span class="flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-indigo-400"></span>
                                        {{ $list->agg_own_count }} eigene
                                    </span>
                                @endif
                                @if($list->agg_competitor_count > 0)
                                    <span class="flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                        {{ $list->agg_competitor_count }} Wettb.
                                    </span>
                                @endif
                                @if($list->agg_errors > 0)
                                    <span class="flex items-center gap-1 text-red-500">
                                        @svg('heroicon-o-exclamation-circle', 'w-3.5 h-3.5')
                                        {{ $list->agg_errors }} Fehler
                                    </span>
                                @endif
                            </div>
                        </div>

                        {{-- Stats Row --}}
                        <div class="px-5 pb-4">
                            <div class="grid grid-cols-4 gap-3">
                                <div class="text-center">
                                    <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Keywords</div>
                                    <div class="text-[15px] font-bold text-gray-900 tabular-nums">{{ number_format($list->agg_keywords) }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Suchvol.</div>
                                    <div class="text-[15px] font-bold text-gray-900 tabular-nums">
                                        @if($list->agg_search_volume >= 1000000)
                                            {{ number_format($list->agg_search_volume / 1000000, 1) }}M
                                        @elseif($list->agg_search_volume >= 1000)
                                            {{ number_format($list->agg_search_volume / 1000, 1) }}K
                                        @else
                                            {{ number_format($list->agg_search_volume) }}
                                        @endif
                                    </div>
                                </div>
                                <div class="text-center">
                                    <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Sichtb.</div>
                                    <div class="text-[15px] font-bold text-indigo-600 tabular-nums">{{ number_format($list->agg_visibility, 0) }}</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Backlinks</div>
                                    <div class="text-[15px] font-bold text-gray-900 tabular-nums">
                                        @if($list->agg_backlinks >= 1000)
                                            {{ number_format($list->agg_backlinks / 1000, 1) }}K
                                        @else
                                            {{ number_format($list->agg_backlinks) }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Top Keywords --}}
                        @if($list->top_keywords->isNotEmpty())
                            <div class="px-5 pb-4">
                                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-2">Top Keywords</div>
                                <div class="space-y-1.5">
                                    @foreach($list->top_keywords as $kw)
                                        <div class="flex items-center justify-between text-[12px]">
                                            <span class="text-gray-700 truncate mr-3">{{ $kw->keyword }}</span>
                                            <div class="flex items-center gap-2 shrink-0">
                                                @if($kw->position)
                                                    <span class="inline-flex items-center justify-center min-w-[24px] h-5 rounded text-[10px] font-bold tabular-nums
                                                        {{ $kw->position <= 3 ? 'bg-green-100 text-green-700' : ($kw->position <= 10 ? 'bg-blue-100 text-blue-700' : ($kw->position <= 20 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600')) }}">
                                                        #{{ $kw->position }}
                                                    </span>
                                                @endif
                                                <span class="text-[10px] text-gray-400 tabular-nums w-12 text-right">
                                                    @if($kw->search_volume >= 1000)
                                                        {{ number_format($kw->search_volume / 1000, 1) }}K
                                                    @else
                                                        {{ number_format($kw->search_volume ?? 0) }}
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </a>

                    {{-- Footer --}}
                    <div class="px-5 py-2.5 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
                        <div class="text-[10px] text-gray-400">
                            @if($list->agg_last_crawled)
                                Aktualisiert {{ \Carbon\Carbon::parse($list->agg_last_crawled)->diffForHumans() }}
                            @else
                                Noch nicht gecrawlt
                            @endif
                        </div>
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button wire:click.prevent="openEditModal({{ $list->id }})" class="text-[11px] text-gray-400 hover:text-indigo-600 transition flex items-center gap-1">
                                @svg('heroicon-o-pencil', 'w-3.5 h-3.5')
                                <span>Bearbeiten</span>
                            </button>
                            <button wire:click.prevent="deleteList({{ $list->id }})" wire:confirm="Liste löschen? Die URLs selbst bleiben erhalten." class="text-[11px] text-gray-400 hover:text-red-500 transition flex items-center gap-1">
                                @svg('heroicon-o-trash', 'w-3.5 h-3.5')
                                <span>Löschen</span>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-16">
                    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                        @svg('heroicon-o-queue-list', 'w-6 h-6 text-gray-400')
                    </div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Noch keine Listen</p>
                    <p class="text-xs text-gray-400 mb-4">Erstelle eine neue Liste, um URLs zu gruppieren und gemeinsam zu analysieren.</p>
                    <x-ui-button variant="primary" size="sm" wire:click="openCreateModal">
                        @svg('heroicon-o-plus', 'w-4 h-4')
                        <span>Erste Liste erstellen</span>
                    </x-ui-button>
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
