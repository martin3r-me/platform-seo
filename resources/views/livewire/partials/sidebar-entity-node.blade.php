{{-- Rekursiver Baum-Knoten = Perspektive. Klick auf den Namen öffnet die Perspektive; Chevron klappt Unterknoten auf. --}}
@props(['node', 'typeIcon' => null, 'depth' => 0])

@php $hasChildren = $node['children_by_type']->isNotEmpty(); @endphp

<div wire:key="entity-{{ $node['entity_id'] }}"
     x-data="{ open: localStorage.getItem('seo.entity.' + {{ $node['entity_id'] }}) === 'true' }"
     class="flex flex-col">
    {{-- Knoten-Zeile: Chevron (Unterknoten) + Name (Perspektive) + Anzahl --}}
    <div class="flex items-center gap-1 py-1 px-2 rounded-md hover:bg-[var(--ui-muted-5)] transition">
        @if($hasChildren)
            <button type="button"
                    @click="open = !open; localStorage.setItem('seo.entity.' + {{ $node['entity_id'] }}, open)"
                    class="w-3 h-3 flex-shrink-0 flex items-center justify-center text-[var(--ui-muted)] transition-transform"
                    :class="open ? 'rotate-90' : ''">
                @svg('heroicon-o-chevron-right', 'w-2.5 h-2.5')
            </button>
        @else
            <span class="w-3 h-3 flex-shrink-0"></span>
        @endif
        <a href="{{ route('seo.perspective', $node['entity_id']) }}" wire:navigate
           class="flex-1 min-w-0 text-[var(--ui-secondary)] hover:text-[var(--ui-primary)] transition">
            <span class="truncate block text-xs font-medium">{{ $node['entity_name'] }}</span>
        </a>
        <span class="text-[10px] tabular-nums text-[var(--ui-muted)] opacity-60">{{ $node['total_items'] }}</span>
    </div>

    {{-- Unterknoten (nur Baum, keine URLs) --}}
    @if($hasChildren)
        <div x-show="open" x-collapse class="flex flex-col ml-3 border-l border-[var(--ui-border)]">
            @foreach($node['children_by_type'] as $typeGroup)
                <div wire:key="entity-{{ $node['entity_id'] }}-type-{{ $typeGroup['type_id'] }}" class="flex flex-col">
                    <div class="text-[9px] uppercase tracking-wider text-[var(--ui-muted)] opacity-60 pl-3 mt-1 mb-0.5">{{ $typeGroup['type_name'] }}</div>
                    @foreach($typeGroup['children'] as $child)
                        @include('seo::livewire.partials.sidebar-entity-node', [
                            'node' => $child,
                            'typeIcon' => $typeGroup['type_icon'] ?? $typeIcon,
                            'depth' => $depth + 1,
                        ])
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>
