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
        <x-ui-sidebar-item :href="route('seo.urls')" :active="request()->routeIs('seo.urls')">
            @svg('heroicon-o-globe-alt', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Alle URLs</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.signals')" :active="request()->routeIs('seo.signals') || request()->routeIs('seo.signals.definitions')">
            @svg('heroicon-o-signal', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Signale</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.clusters')" :active="request()->routeIs('seo.clusters') || request()->routeIs('seo.clusters.show')">
            @svg('heroicon-o-squares-2x2', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Cluster</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.briefs')" :active="request()->routeIs('seo.briefs') || request()->routeIs('seo.briefs.show')">
            @svg('heroicon-o-document-text', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Content-Briefs</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('seo.portfolios')" :active="request()->routeIs('seo.portfolios') || request()->routeIs('seo.portfolios.show')">
            @svg('heroicon-o-rocket-launch', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Wirkungsräume</span>
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

    {{-- Listen: die Markt-/Themen-Achse (quer zum Org-Baum) — Index + einzelne Listen --}}
    <x-ui-sidebar-list label="Listen">
        <x-ui-sidebar-item :href="route('seo.lists')" :active="request()->routeIs('seo.lists')">
            @svg('heroicon-o-queue-list', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Alle Listen</span>
        </x-ui-sidebar-item>
        @foreach($lists as $list)
            <x-ui-sidebar-item wire:key="list-{{ $list->id }}"
                               :href="route('seo.lists.show', $list->id)"
                               :active="request()->routeIs('seo.lists.show') && (request()->route('seoUrlList')?->id ?? null) == $list->id">
                @svg('heroicon-o-rectangle-stack', 'w-4 h-4 text-[var(--ui-secondary)]')
                <span class="ml-2 text-sm truncate">{{ $list->name }}</span>
                <x-slot name="trailing"><span class="text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $list->urls_count }}</span></x-slot>
            </x-ui-sidebar-item>
        @endforeach
    </x-ui-sidebar-list>

    {{-- Quellen & Ablage: abgeleitete Perspektiven (Quelle/Status), keine URL-Listen --}}
    @if($sourcePerspectives->isNotEmpty() || $unassignedCount > 0)
        <x-ui-sidebar-list label="Quellen & Ablage">
            @foreach($sourcePerspectives as $src)
                <x-ui-sidebar-item wire:key="src-{{ $src['module'] }}" :href="route('seo.perspective.source', $src['module'])">
                    @svg('heroicon-o-cube', 'w-4 h-4 text-[var(--ui-secondary)]')
                    <span class="ml-2 text-sm">{{ $src['label'] }}</span>
                    <x-slot name="trailing"><span class="text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $src['count'] }}</span></x-slot>
                </x-ui-sidebar-item>
            @endforeach
            @if($unassignedCount > 0)
                <x-ui-sidebar-item :href="route('seo.perspective.unassigned')">
                    @svg('heroicon-o-inbox', 'w-4 h-4 text-[var(--ui-secondary)]')
                    <span class="ml-2 text-sm">Nicht eingeordnet</span>
                    <x-slot name="trailing"><span class="text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $unassignedCount }}</span></x-slot>
                </x-ui-sidebar-item>
            @endif
        </x-ui-sidebar-list>
    @endif

    @include('seo::partials.help-concept-modal')
</div>
