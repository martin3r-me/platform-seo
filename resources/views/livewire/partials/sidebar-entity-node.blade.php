{{-- Rekursiver Entity-Knoten für SEO Sidebar-Baum --}}
@props(['node', 'typeIcon' => null, 'depth' => 0])

<div wire:key="entity-{{ $node['entity_id'] }}"
     x-data="{ open: localStorage.getItem('seo.entity.' + {{ $node['entity_id'] }}) === 'true' }"
     class="flex flex-col">
    {{-- Entity-Zeile --}}
    <button type="button"
            @click="open = !open; localStorage.setItem('seo.entity.' + {{ $node['entity_id'] }}, open)"
            class="flex items-center gap-1 py-1 px-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)] transition w-full text-left group">
        <span class="w-3 h-3 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--ui-muted)]"
              :class="open ? 'rotate-90' : ''">
            @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
        </span>
        <span class="truncate text-xs font-medium">{{ $node['entity_name'] }}</span>
        <span class="ml-auto text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $node['total_items'] }}</span>
    </button>

    {{-- Aufgeklappter Inhalt --}}
    <div x-show="open" x-collapse class="flex flex-col ml-3 border-l border-[var(--ui-border)]">
        {{-- 1. Listen --}}
        @foreach($node['lists'] as $list)
            <a wire:key="entity-{{ $node['entity_id'] }}-list-{{ $list->id }}"
               href="{{ route('seo.lists.show', $list) }}"
               wire:navigate
               title="{{ $list->name ?: 'Liste' }}"
               class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition">
                @svg('heroicon-o-queue-list', 'w-3 h-3 flex-shrink-0 opacity-40')
                <span class="truncate text-[11px]">{{ $list->name ?: 'Liste' }}</span>
                @isset($list->urls_count)<span class="ml-auto text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $list->urls_count }}</span>@endisset
            </a>
        @endforeach

        {{-- 2. URLs --}}
        @foreach($node['urls'] as $url)
            <a wire:key="entity-{{ $node['entity_id'] }}-url-{{ $url->id }}"
               href="{{ route('seo.urls.show', $url) }}"
               wire:navigate
               title="{{ $url->url }}"
               class="flex items-center gap-1.5 py-0.5 pl-3 pr-2 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition truncate">
                @svg('heroicon-o-globe-alt', 'w-3 h-3 flex-shrink-0 opacity-40')
                <span class="truncate text-[11px]">{{ $url->display_label }}</span>
            </a>
        @endforeach

        {{-- 3. Kind-Entities nach Typ gruppiert --}}
        @foreach($node['children_by_type'] as $typeGroup)
            <div wire:key="entity-{{ $node['entity_id'] }}-type-{{ $typeGroup['type_id'] }}"
                 x-data="{ groupOpen: localStorage.getItem('seo.entity.' + {{ $node['entity_id'] }} + '.type.' + {{ $typeGroup['type_id'] }}) !== 'false' }"
                 class="flex flex-col">
                @if($node['children_by_type']->count() > 1 || $node['lists']->isNotEmpty() || $node['urls']->isNotEmpty())
                    <button type="button"
                            @click="groupOpen = !groupOpen; localStorage.setItem('seo.entity.' + {{ $node['entity_id'] }} + '.type.' + {{ $typeGroup['type_id'] }}, groupOpen)"
                            class="flex items-center gap-1 mt-1 mb-0.5 pl-2.5 pr-2 w-full text-left group cursor-pointer">
                        <span class="w-2.5 h-2.5 flex-shrink-0 flex items-center justify-center transition-transform text-[var(--ui-muted)] opacity-50"
                              :class="groupOpen ? 'rotate-90' : ''">
                            @svg('heroicon-o-chevron-right', 'w-2 h-2')
                        </span>
                        <span class="text-[9px] uppercase tracking-wider text-[var(--ui-muted)] opacity-60 group-hover:opacity-100 transition-opacity">
                            {{ $typeGroup['type_name'] }}
                        </span>
                    </button>
                @endif
                <div x-show="groupOpen" x-collapse class="flex flex-col">
                    @foreach($typeGroup['children'] as $child)
                        @include('seo::livewire.partials.sidebar-entity-node', [
                            'node' => $child,
                            'typeIcon' => $typeGroup['type_icon'] ?? $typeIcon,
                            'depth' => $depth + 1,
                        ])
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
