<div x-data="{ helpOpen: false }">
    {{-- Modul-Header + Konzept-Anker („?") --}}
    <div x-show="!collapsed" class="p-3 flex items-center justify-between border-b border-[var(--ui-border)] mb-2">
        <span class="text-sm italic text-[var(--ui-secondary)] uppercase">SEO</span>
        <button type="button" @click="helpOpen = true"
                class="text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition"
                title="So funktioniert SEO">
            @svg('heroicon-o-question-mark-circle', 'w-4 h-4')
        </button>
    </div>

    {{-- Linsen-Navigation (alle Perspektiven auf URLs + Signale) --}}
    <x-ui-sidebar-list label="Navigation">
        <x-ui-sidebar-item :href="route('seo.dashboard')" :active="request()->routeIs('seo.dashboard')">
            @svg('heroicon-o-chart-bar-square', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Dashboard</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.recommendations')" :active="request()->routeIs('seo.recommendations')">
            @svg('heroicon-o-light-bulb', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Empfehlungen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.clusters')" :active="request()->routeIs('seo.clusters*')">
            @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Cluster</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.lists')" :active="request()->routeIs('seo.lists')">
            @svg('heroicon-o-queue-list', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Listen</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.urls')" :active="request()->routeIs('seo.urls')">
            @svg('heroicon-o-globe-alt', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">URLs</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.competitors')" :active="request()->routeIs('seo.competitors')">
            @svg('heroicon-o-user-group', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Wettbewerber</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Der Baum = der Perspektiv-Wähler. Jeder Knoten/Typ ist eine Perspektive. --}}
    <div x-show="!collapsed" class="mt-2">
        @foreach($entityTypeGroups as $typeGroup)
            <x-ui-sidebar-list wire:key="type-group-{{ $typeGroup['type_id'] }}" :label="$typeGroup['type_name']">
                @foreach($typeGroup['entities'] as $entityNode)
                    @include('seo::livewire.partials.sidebar-entity-node', [
                        'node' => $entityNode,
                        'typeIcon' => $typeGroup['type_icon'] ?? null,
                    ])
                @endforeach
            </x-ui-sidebar-list>
        @endforeach

        {{-- Leer-Zustand --}}
        @if($entityTypeGroups->isEmpty())
            <div class="px-3 py-2 text-xs text-[var(--ui-muted)]">
                Noch keine Knoten mit SEO-URLs. Hänge URLs im URL-Detail an einen Org-Knoten, dann erscheint hier der Baum.
            </div>
        @endif
    </div>

    @include('seo::partials.help-concept-modal')
</div>
