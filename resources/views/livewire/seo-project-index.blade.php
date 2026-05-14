<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="SEO Projekte" icon="heroicon-o-magnifying-glass-circle" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle'],
            ['label' => 'Projekte'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="$set('showCreateModal', true)">
                @svg('heroicon-o-plus', 'w-4 h-4')
                <span>Neues Projekt</span>
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-ui-page-container>

        {{-- Hero Stats --}}
        <div class="py-12 md:py-16">
            <div class="flex items-baseline gap-6 flex-wrap">
                <div>
                    <span class="text-5xl md:text-6xl font-light text-gray-900 tracking-tight">{{ $projects->count() }}</span>
                    <span class="text-lg text-gray-400 ml-2">{{ $projects->count() === 1 ? 'Projekt' : 'Projekte' }}</span>
                </div>
                <div class="flex items-baseline gap-6 text-base text-gray-300">
                    <span>{{ $projects->sum('keywords_count') }} Keywords</span>
                </div>
            </div>
        </div>

        {{-- Project Cards --}}
        @if($projects->isNotEmpty())
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($projects as $project)
                    <a href="{{ route('seo.projects.show', $project) }}" wire:navigate
                       class="group relative flex flex-col bg-white rounded-2xl border border-gray-100 hover:border-gray-200 hover:shadow-lg transition-all duration-300 overflow-hidden">
                        <div class="h-1.5 bg-indigo-500"></div>
                        <div class="flex-1 p-6">
                            <h2 class="text-xl font-semibold tracking-tight text-gray-900">{{ $project->name }}</h2>
                            @if($project->domain)
                                <p class="text-sm text-gray-400 mt-1">{{ $project->domain }}</p>
                            @endif
                            @if($project->description)
                                <p class="text-sm text-gray-400 leading-relaxed mt-1.5 line-clamp-2">{{ $project->description }}</p>
                            @endif
                            <div class="mt-4 flex items-center gap-4 text-sm text-gray-400">
                                <span>{{ $project->keywords_count }} Keywords</span>
                                @if($project->industry_preset)
                                    <span class="px-2 py-0.5 bg-gray-100 rounded text-xs">{{ config("seo.industry_presets.{$project->industry_preset}.label", $project->industry_preset) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="px-6 py-3.5 border-t border-gray-50 flex items-center justify-between">
                            <span class="text-[12px] text-gray-300">{{ $project->updated_at->format('d. M Y') }}</span>
                            <span class="text-xs text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity duration-200">
                                &Ouml;ffnen &rarr;
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="py-20 text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-gray-50 mb-6">
                    @svg('heroicon-o-magnifying-glass-circle', 'w-10 h-10 text-gray-300')
                </div>
                <h3 class="text-2xl font-light text-gray-900 mb-2">Noch keine Projekte</h3>
                <p class="text-base text-gray-400 mb-8">Erstelle dein erstes SEO-Projekt, um loszulegen.</p>
                <x-ui-button variant="primary" size="sm" wire:click="$set('showCreateModal', true)">
                    @svg('heroicon-o-plus', 'w-4 h-4')
                    <span>Neues Projekt</span>
                </x-ui-button>
            </div>
        @endif

    </x-ui-page-container>

    {{-- Create Modal --}}
    <x-ui-modal wire:model="showCreateModal" title="Neues SEO-Projekt">
        <form wire:submit="createProject">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" wire:model="newProjectName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="z.B. Hauptwebsite SEO" autofocus>
                    @error('newProjectName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Domain (optional)</label>
                    <input type="text" wire:model="newProjectDomain" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="z.B. example.com">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branche (optional)</label>
                    <select wire:model="newProjectPreset" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">Keine Auswahl</option>
                        @foreach($presets as $key => $preset)
                            <option value="{{ $key }}">{{ $preset['label'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showCreateModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" type="submit">Erstellen</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>
</x-ui-page>
