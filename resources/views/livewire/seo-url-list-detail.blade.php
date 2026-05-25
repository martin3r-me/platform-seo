<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $seoUrlList->name }}" icon="heroicon-o-queue-list" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Listen', 'route' => 'seo.lists'],
            ['label' => $seoUrlList->name],
        ]">
            <x-ui-button variant="secondary" size="sm" wire:click="openAddUrlsModal">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>URLs hinzufügen</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'lists'])
    </x-slot>

    <x-ui-page-container>

        {{-- Sub-Navigation --}}
        <div class="flex items-center gap-1 border-b border-gray-200 mb-6">
            <a href="{{ route('seo.lists.show', $seoUrlList) }}" wire:navigate
               class="px-4 py-3 text-[13px] font-medium text-[#166EE1] border-b-2 border-[#166EE1]">
                Übersicht
            </a>
            <a href="{{ route('seo.lists.competitors', $seoUrlList) }}" wire:navigate
               class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">
                Wettbewerber
            </a>
            <a href="{{ route('seo.lists.cannibalization', $seoUrlList) }}" wire:navigate
               class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">
                Kannibalisierung
            </a>
            <a href="{{ route('seo.lists.signals', $seoUrlList) }}" wire:navigate
               class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">
                Signale
            </a>
        </div>

        @if($seoUrlList->description)
            <p class="text-[13px] text-gray-500 mb-5">{{ $seoUrlList->description }}</p>
        @endif

        {{-- Aggregated Metrics --}}
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Sichtbarkeit</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($aggregated['visibility_score'], 1) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Keywords</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($aggregated['keyword_count']) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Suchvolumen</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($aggregated['total_search_volume']) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Backlinks</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($aggregated['backlink_count']) }}</div>
            </div>
        </div>

        {{-- URLs Table + Detail Panel --}}
        <div class="flex gap-0 items-start">
            {{-- Left: URL List --}}
            <div class="flex-1 min-w-0 bg-white rounded-l-lg border border-gray-200 {{ $this->selectedUrl ? 'border-r-0' : 'rounded-r-lg' }} overflow-hidden">
                <table class="w-full text-[13px]">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                            <th class="px-4 py-2.5 text-left">URL</th>
                            <th class="px-4 py-2.5 text-right">Kinder</th>
                            <th class="px-4 py-2.5 text-right">KWs</th>
                            <th class="px-4 py-2.5 text-right">SV</th>
                            <th class="px-4 py-2.5 text-right">Sicht.</th>
                            <th class="px-4 py-2.5 text-right">Links</th>
                            <th class="px-4 py-2.5 w-8"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($listUrls as $url)
                            <tr wire:key="lurl-{{ $url->id }}"
                                wire:click="selectUrl({{ $url->id }})"
                                class="cursor-pointer transition-colors {{ $selectedUrlId === $url->id ? 'bg-blue-50' : 'hover:bg-gray-50' }}">
                                <td class="px-4 py-2.5">
                                    <div class="font-medium text-gray-900 truncate max-w-xs">{{ $url->path ?: '/' }}</div>
                                    <div class="text-[10px] text-gray-400">{{ $url->domain }}</div>
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
                                <td class="px-4 py-2.5 text-right font-medium text-gray-800 tabular-nums">{{ $url->agg_keywords }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    @include('seo::partials.sv-badge', ['volume' => $url->agg_search_volume])
                                </td>
                                <td class="px-4 py-2.5 text-right font-medium text-gray-800 tabular-nums">{{ number_format($url->agg_visibility, 1) }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-500 tabular-nums">{{ $url->agg_backlinks }}</td>
                                <td class="px-4 py-2.5 text-right" wire:click.stop>
                                    <button wire:click="removeUrlFromList({{ $url->id }})" class="text-gray-300 hover:text-red-500 transition" title="Aus Liste entfernen">
                                        @svg('heroicon-o-x-mark', 'w-4 h-4')
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-16 text-center">
                                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                        @svg('heroicon-o-globe-alt', 'w-5 h-5 text-gray-400')
                                    </div>
                                    <p class="text-sm text-gray-500 font-medium mb-1">Noch keine URLs</p>
                                    <p class="text-xs text-gray-400">Füge URLs hinzu, um sie gemeinsam zu analysieren.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Right: Detail Panel --}}
            @if($this->selectedUrl)
                <div class="w-[400px] shrink-0 bg-white rounded-r-lg border border-gray-200 overflow-y-auto sticky top-0" style="max-height: calc(100vh - 120px);">
                    {{-- Panel Header --}}
                    <div class="sticky top-0 z-10 bg-white border-b border-gray-100 px-5 py-3 flex items-center justify-between">
                        <div class="min-w-0">
                            <h3 class="text-[13px] font-semibold text-gray-900 truncate">{{ $this->selectedUrl->path ?: '/' }}</h3>
                            <div class="text-[10px] text-gray-400 truncate">{{ $this->selectedUrl->domain }}</div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ml-3">
                            <a href="{{ route('seo.urls.show', $this->selectedUrl) }}" wire:navigate class="text-[11px] text-indigo-600 hover:text-indigo-800 font-medium">Öffnen</a>
                            <button wire:click="selectUrl({{ $this->selectedUrl->id }})" class="text-gray-400 hover:text-gray-600 p-1">
                                @svg('heroicon-o-x-mark', 'w-4 h-4')
                            </button>
                        </div>
                    </div>

                    {{-- URL Quick Stats --}}
                    <div class="p-5 border-b border-gray-100">
                        <div class="grid grid-cols-2 gap-2">
                            <div class="bg-gray-50 rounded-md px-3 py-2">
                                <div class="text-[10px] text-gray-400 uppercase">Keywords</div>
                                <div class="text-sm font-semibold text-gray-800">{{ $this->selectedUrl->keyword_count }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-md px-3 py-2">
                                <div class="text-[10px] text-gray-400 uppercase">Suchvolumen</div>
                                <div class="text-sm font-semibold text-gray-800">{{ number_format($this->selectedUrl->total_search_volume) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-md px-3 py-2">
                                <div class="text-[10px] text-gray-400 uppercase">Sichtbarkeit</div>
                                <div class="text-sm font-semibold text-gray-800">{{ number_format($this->selectedUrl->visibility_score, 1) }}</div>
                            </div>
                            <div class="bg-gray-50 rounded-md px-3 py-2">
                                <div class="text-[10px] text-gray-400 uppercase">Backlinks</div>
                                <div class="text-sm font-semibold text-gray-800">{{ $this->selectedUrl->backlink_count }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Top Keywords for this URL --}}
                    <div class="p-5">
                        <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-3">Top Keywords</h4>
                        @if($this->selectedUrlKeywords->isNotEmpty())
                            <div class="space-y-0 rounded-lg border border-gray-100 overflow-hidden">
                                <table class="w-full text-[11px]">
                                    <thead>
                                        <tr class="bg-gray-50 text-gray-500 font-medium">
                                            <th class="px-3 py-1.5 text-left">Keyword</th>
                                            <th class="px-3 py-1.5 text-right">Pos</th>
                                            <th class="px-3 py-1.5 text-right">SV</th>
                                            <th class="px-3 py-1.5 text-right">KD</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-50">
                                        @foreach($this->selectedUrlKeywords as $kw)
                                            @php $pos = $kw->urls->first()?->pivot->position; @endphp
                                            <tr class="hover:bg-blue-50/30 transition-colors">
                                                <td class="px-3 py-1.5 font-medium text-gray-800 truncate max-w-[160px]">{{ $kw->keyword }}</td>
                                                <td class="px-3 py-1.5 text-right">
                                                    @include('seo::partials.position-badge', ['position' => $pos, 'change' => null])
                                                </td>
                                                <td class="px-3 py-1.5 text-right text-gray-600 tabular-nums">{{ $kw->search_volume ? number_format($kw->search_volume) : '—' }}</td>
                                                <td class="px-3 py-1.5 text-right">
                                                    @include('seo::partials.kd-badge', ['value' => $kw->keyword_difficulty])
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-[12px] text-gray-400 text-center py-4">Keine Keywords für diese URL.</div>
                        @endif
                    </div>
                </div>
            @endif
        </div>

    </x-ui-page-container>

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
