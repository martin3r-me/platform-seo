<div>
    <div x-show="!collapsed" class="px-3 pt-3 pb-2 border-b border-[#2C3135] mb-2">
        <span class="text-[10px] uppercase tracking-widest text-gray-500 font-medium">SEO</span>
    </div>

    <div x-show="!collapsed" class="px-2 mb-1">
        <a href="{{ route('seo.dashboard') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-chart-bar-square', 'w-4 h-4')
            <span>Dashboard</span>
        </a>
        <a href="{{ route('seo.lists') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-queue-list', 'w-4 h-4')
            <span>Listen</span>
        </a>
        <a href="{{ route('seo.urls') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-globe-alt', 'w-4 h-4')
            <span>URLs</span>
        </a>
    </div>

    {{-- Collapsed View --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('seo.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Dashboard">
                @svg('heroicon-o-chart-bar-square', 'w-5 h-5')
            </a>
            <a href="{{ route('seo.lists') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Listen">
                @svg('heroicon-o-queue-list', 'w-5 h-5')
            </a>
            <a href="{{ route('seo.urls') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="URLs">
                @svg('heroicon-o-globe-alt', 'w-5 h-5')
            </a>
        </div>
    </div>

    {{-- Entity-basierte Gruppierung --}}
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
