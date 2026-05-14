<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="{{ $seoProject->name }}" icon="heroicon-o-magnifying-glass-circle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.projects.index'],
            ['label' => $seoProject->name],
        ]">
            <x-ui-button variant="secondary" size="sm" wire:click="openEditModal">
                @svg('heroicon-o-pencil', 'w-4 h-4')
                <span>Bearbeiten</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>

        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-1 border-b border-gray-100 mb-8">
            <a href="{{ route('seo.projects.show', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600">
                Dashboard
            </a>
            <a href="{{ route('seo.projects.keywords', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">
                Keywords
            </a>
            <a href="{{ route('seo.projects.rankings', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">
                Rankings
            </a>
            <a href="{{ route('seo.projects.competitors', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">
                Wettbewerber
            </a>
            <a href="{{ route('seo.projects.signals', $seoProject) }}" wire:navigate
               class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">
                Signale
            </a>
        </div>

        {{-- Stats Grid --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 mb-10">
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">Keywords</div>
                <div class="text-3xl font-light text-gray-900">{{ number_format($keywordSummary['total_keywords']) }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $keywordSummary['with_metrics'] }} mit Metriken</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">Suchvolumen</div>
                <div class="text-3xl font-light text-gray-900">{{ number_format($keywordSummary['total_search_volume']) }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ $keywordSummary['clusters_count'] }} Cluster</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">Visibility Score</div>
                <div class="text-3xl font-light text-gray-900">{{ $visibility['percentage'] }}%</div>
                <div class="text-xs text-gray-400 mt-1">{{ $visibility['keywords_with_position'] }} mit Position</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">Budget</div>
                <div class="text-3xl font-light text-gray-900">
                    @if($budgetSummary['percentage'] !== null)
                        {{ $budgetSummary['percentage'] }}%
                    @else
                        &mdash;
                    @endif
                </div>
                <div class="text-xs text-gray-400 mt-1">{{ number_format($budgetSummary['remaining_cents'] / 100, 2) }} &euro; verbleibend</div>
            </div>
        </div>

        {{-- Search Intent Distribution --}}
        @if(!empty($keywordSummary['intents']))
            <div class="mb-10">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Search Intent Verteilung</h3>
                <div class="flex items-center gap-3 flex-wrap">
                    @foreach($keywordSummary['intents'] as $intent => $count)
                        <span class="px-3 py-1.5 bg-gray-100 rounded-full text-xs text-gray-600">
                            {{ ucfirst($intent) }}: {{ $count }}
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recent Signals --}}
        @if($recentSignals->isNotEmpty())
            <div class="mb-10">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-medium text-gray-700">Aktuelle Signale</h3>
                    <a href="{{ route('seo.projects.signals', $seoProject) }}" wire:navigate class="text-xs text-indigo-600 hover:underline">Alle anzeigen</a>
                </div>
                <div class="space-y-2">
                    @foreach($recentSignals as $signal)
                        <div class="flex items-center gap-3 p-3 bg-white rounded-lg border border-gray-100">
                            <span class="w-2 h-2 rounded-full flex-shrink-0
                                @if($signal->severity === 'warning') bg-amber-500
                                @elseif($signal->severity === 'watch') bg-blue-500
                                @else bg-gray-400
                                @endif"></span>
                            <div class="min-w-0 flex-1">
                                <span class="text-sm text-gray-700 truncate block">{{ $signal->title }}</span>
                                <span class="text-xs text-gray-400">{{ $signal->detected_at->format('d.m.Y') }}</span>
                            </div>
                            <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 rounded text-gray-500">{{ $signal->signal_type }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </x-ui-page-container>

    {{-- Edit Modal --}}
    <x-ui-modal wire:model="showEditModal" title="Projekt bearbeiten">
        <form wire:submit="saveProject">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" wire:model="editName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @error('editName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Beschreibung</label>
                    <textarea wire:model="editDescription" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domain</label>
                    <input type="text" wire:model="editDomain" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="danger" size="sm" wire:click="deleteProject" wire:confirm="Projekt wirklich l&ouml;schen?">L&ouml;schen</x-ui-button>
                <div class="flex-1"></div>
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showEditModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">Speichern</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>
</x-ui-page>
