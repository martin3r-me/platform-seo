<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Signale" icon="heroicon-o-signal" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Signale', 'route' => 'seo.signals'],
            ['label' => 'Definitionen'],
        ]">
            <x-ui-button variant="primary" size="sm" wire:click="openCreate">
                @svg('heroicon-o-plus', 'w-4 h-4')
                Neue Definition
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-[color:var(--nx-text)]">Signal-Definitionen</h1>
            <p class="text-[13px] text-[color:var(--nx-muted)] mt-0.5">Deklariere, wann ein SEO-Signal entsteht. Muster + Bedingungen + Geltungsbereich — bearbeitbar hier oder per KI-Tool.</p>
        </div>

        @php($sevColor = ['critical' => 'var(--nx-danger)', 'warning' => 'var(--nx-warning)', 'watch' => 'var(--nx-info)', 'info' => 'var(--nx-faint)'])

        @if($definitions->isNotEmpty())
            <div class="space-y-2">
                @foreach($definitions as $def)
                    <x-nx-card class="{{ $def->is_active ? '' : 'opacity-60' }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $sevColor[$def->severity] ?? 'var(--nx-faint)' }}"></span>
                                    <span class="font-medium text-[color:var(--nx-text)] truncate">{{ $def->name }}</span>
                                    @unless($def->is_active)
                                        <span class="text-[10px] uppercase tracking-wide text-[color:var(--nx-faint)]">pausiert</span>
                                    @endunless
                                </div>
                                @if($def->description)
                                    <p class="mt-1 text-xs text-[color:var(--nx-muted)] line-clamp-2">{{ $def->description }}</p>
                                @endif
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-[color:var(--nx-muted)]">
                                    <span class="tabular-nums">{{ $catalog[$def->pattern_type]['label'] ?? $def->pattern_type }}</span>
                                    <span class="text-[color:var(--nx-faint)]">·</span>
                                    <span>{{ ['all' => 'Ganzes Portfolio', 'entity' => 'Entity', 'entity_subtree' => 'Entity + Subtree', 'list' => 'Liste'][$def->scope_type] ?? $def->scope_type }}</span>
                                    <span class="text-[color:var(--nx-faint)]">·</span>
                                    <span>{{ ['every_snapshot' => 'jeder Snapshot', 'daily' => 'täglich', 'weekly' => 'wöchentlich'][$def->frequency] ?? $def->frequency }}</span>
                                    @if(!empty($def->conditions))
                                        <span class="text-[color:var(--nx-faint)]">·</span>
                                        <span class="font-mono text-[10px] text-[color:var(--nx-faint)] truncate">{{ collect($def->conditions)->map(fn($v,$k) => "$k=$v")->implode(' ') }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-3">
                                <span class="text-xs tabular-nums text-[color:var(--nx-muted)]" title="Bisher gefeuerte Signale">{{ $def->signals_count }} Signale</span>
                                <button wire:click="toggleActive({{ $def->id }})"
                                        class="text-[11px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] transition">
                                    {{ $def->is_active ? 'Pausieren' : 'Aktivieren' }}
                                </button>
                                <button wire:click="openEdit({{ $def->id }})"
                                        class="text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] transition" title="Bearbeiten">
                                    @svg('heroicon-o-pencil-square', 'w-4 h-4')
                                </button>
                                <button wire:click="deleteDefinition({{ $def->id }})" wire:confirm="Definition löschen? Bereits gefeuerte Signale bleiben erhalten."
                                        class="text-[color:var(--nx-muted)] hover:text-[color:var(--nx-danger)] transition" title="Löschen">
                                    @svg('heroicon-o-trash', 'w-4 h-4')
                                </button>
                            </div>
                        </div>
                    </x-nx-card>
                @endforeach
            </div>
        @else
            <x-nx-empty icon="heroicon-o-signal">Noch keine Signal-Definitionen. Leg die erste an — z.&nbsp;B. „Griffweite" für Keywords auf Position 4–10.</x-nx-empty>
        @endif

    </x-ui-page-container>

    {{-- Create/Edit Modal --}}
    <x-ui-modal wire:model="showModal" title="{{ $editingId ? 'Definition bearbeiten' : 'Neue Signal-Definition' }}">
        <form wire:submit="save">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <input type="text" wire:model="name" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="z.B. Griffweite Caterer-Keywords" autofocus>
                    @error('name') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Muster</label>
                    <select wire:model.live="patternType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach($catalog as $key => $meta)
                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-500 mt-1">{{ $catalog[$patternType]['description'] ?? '' }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Severity</label>
                        <select wire:model="severity" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="info">info</option>
                            <option value="watch">watch</option>
                            <option value="warning">warning</option>
                            <option value="critical">critical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Frequenz</label>
                        <select wire:model="frequency" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="every_snapshot">jeder Snapshot</option>
                            <option value="daily">täglich</option>
                            <option value="weekly">wöchentlich</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Geltungsbereich</label>
                        <select wire:model.live="scopeType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            <option value="all">Ganzes Portfolio</option>
                            <option value="entity">Entity</option>
                            <option value="entity_subtree">Entity + Subtree</option>
                            <option value="list">Liste</option>
                        </select>
                    </div>
                    @if($scopeType === 'list')
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Liste</label>
                            <select wire:model="scopeValue" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">— Liste wählen —</option>
                                @foreach($lists as $l)
                                    <option value="{{ $l->id }}">{{ $l->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @elseif(in_array($scopeType, ['entity', 'entity_subtree']))
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Entity</label>
                            <select wire:model="scopeValue" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                <option value="">— Entity wählen —</option>
                                @foreach($entities as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Bedingungen (JSON)</label>
                    <textarea wire:model="conditionsJson" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono" spellcheck="false"></textarea>
                    @error('conditionsJson') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    <p class="text-xs text-gray-500 mt-1">Tunbare Parameter des Musters. Vorbelegt mit sinnvollen Defaults.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notiz (optional)</label>
                    <textarea wire:model="description" rows="2" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="Wofür ist diese Definition?"></textarea>
                </div>
            </div>
            <x-slot name="footer">
                <x-ui-button variant="secondary" size="sm" wire:click="$set('showModal', false)">Abbrechen</x-ui-button>
                <x-ui-button variant="primary" size="sm" wire:click="save">{{ $editingId ? 'Speichern' : 'Erstellen' }}</x-ui-button>
            </x-slot>
        </form>
    </x-ui-modal>
</x-ui-page>
