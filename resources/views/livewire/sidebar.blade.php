<div>
    {{-- Modul-Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        SEO
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

    {{-- Kontext: Entity-basierte Gruppierung (URLs/Listen am Org-Baum) --}}
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

        {{-- Listen ohne Kontext --}}
        @if($unlinkedLists->isNotEmpty())
            <x-ui-sidebar-list label="Listen · ohne Kontext">
                @foreach($unlinkedLists as $list)
                    <a wire:key="unlinked-list-{{ $list->id }}"
                       href="{{ route('seo.lists.show', $list) }}"
                       wire:navigate
                       title="{{ $list->name ?: 'Liste' }}"
                       class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition">
                        @svg('heroicon-o-queue-list', 'w-3 h-3 flex-shrink-0 opacity-40')
                        <span class="truncate text-[11px]">{{ $list->name ?: 'Liste' }}</span>
                        @isset($list->urls_count)<span class="ml-auto text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $list->urls_count }}</span>@endisset
                    </a>
                @endforeach
            </x-ui-sidebar-list>
        @endif

        {{-- Unverknüpfte URLs (nur anzeigen wenn welche existieren, begrenzt auf 20) --}}
        @if($unlinkedUrls->isNotEmpty())
            <x-ui-sidebar-list label="URLs · ohne Kontext">
                @foreach($unlinkedUrls->take(20) as $url)
                    <a wire:key="unlinked-url-{{ $url->id }}"
                       href="{{ route('seo.urls.show', $url) }}"
                       wire:navigate
                       title="{{ $url->url }}"
                       class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                        @svg('heroicon-o-globe-alt', 'w-3 h-3 flex-shrink-0 opacity-40')
                        <span class="truncate text-[11px]">{{ $url->display_label }}</span>
                    </a>
                @endforeach
                @if($unlinkedUrls->count() > 20)
                    <a href="{{ route('seo.urls') }}" wire:navigate
                       class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-muted)] hover:text-[var(--ui-secondary)] transition text-[10px]">
                        +{{ $unlinkedUrls->count() - 20 }} weitere
                    </a>
                @endif
            </x-ui-sidebar-list>
        @endif

        {{-- Leer-Zustand --}}
        @if($entityTypeGroups->isEmpty() && $unlinkedLists->isEmpty() && $unlinkedUrls->isEmpty())
            <div class="px-3 py-1 text-xs text-[var(--ui-muted)]">
                Keine Listen oder URLs
            </div>
        @endif
    </div>
</div>
