<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Signale" icon="heroicon-o-signal" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Signale'],
        ]">
            <x-ui-button variant="secondary" size="sm" :href="route('seo.signals.definitions')">
                @svg('heroicon-o-adjustments-horizontal', 'w-4 h-4')
                Definitionen
            </x-ui-button>
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        <div class="mb-5">
            <h1 class="text-lg font-semibold text-[color:var(--nx-text)]">Signale</h1>
            <p class="text-[13px] text-[color:var(--nx-muted)] mt-0.5">Was gerade Aufmerksamkeit braucht — quer über alle Kunden. Definition-getriebene zuerst.</p>
        </div>

        {{-- Status-Tabs --}}
        @php($tabs = ['new' => 'Offen', 'acknowledged' => 'Quittiert', 'resolved' => 'Erledigt'])
        <div class="mb-4 flex items-center gap-1 border-b border-[color:var(--nx-line)]">
            @foreach($tabs as $key => $label)
                <button wire:click="setStatus('{{ $key }}')"
                        class="relative -mb-px px-3 py-2 text-[13px] transition {{ $filterStatus === $key ? 'text-[color:var(--nx-text)] border-b-2 border-[color:var(--nx-text)] font-medium' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">
                    {{ $label }}
                    <span class="ml-1 tabular-nums text-[color:var(--nx-faint)]">{{ $statusCounts[$key] ?? 0 }}</span>
                </button>
            @endforeach

            <div class="ml-auto flex items-center gap-2">
                <select wire:model.live="filterSeverity" class="border border-[color:var(--nx-line)] rounded-md px-2 py-1 text-xs bg-transparent text-[color:var(--nx-muted)]">
                    <option value="">Alle Severity</option>
                    <option value="critical">critical</option>
                    <option value="warning">warning</option>
                    <option value="watch">watch</option>
                    <option value="info">info</option>
                </select>
                <select wire:model.live="filterPattern" class="border border-[color:var(--nx-line)] rounded-md px-2 py-1 text-xs bg-transparent text-[color:var(--nx-muted)]">
                    <option value="">Alle Muster</option>
                    @foreach($catalog as $key => $meta)
                        <option value="{{ $key }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @php($sevColor = ['critical' => 'var(--nx-danger)', 'warning' => 'var(--nx-warning)', 'watch' => 'var(--nx-info)', 'info' => 'var(--nx-faint)', 'opportunity' => 'var(--nx-success)'])

        @if($signals->isNotEmpty())
            <div class="space-y-2">
                @foreach($signals as $signal)
                    <x-nx-card wire:key="sig-{{ $signal->id }}">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $sevColor[$signal->severity] ?? 'var(--nx-faint)' }}"></span>
                                    <span class="font-medium text-[color:var(--nx-text)] truncate">{{ $signal->title }}</span>
                                </div>
                                @if($signal->description)
                                    <p class="mt-1 text-xs text-[color:var(--nx-muted)] line-clamp-2">{{ $signal->description }}</p>
                                @endif
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] text-[color:var(--nx-muted)]">
                                    @if($signal->url)
                                        <a href="{{ route('seo.urls.show', $signal->url->id) }}" wire:navigate class="hover:text-[color:var(--nx-text)] truncate max-w-full inline-block align-bottom">{{ $signal->url->url }}</a>
                                    @elseif($signal->keyword)
                                        <span class="truncate">„{{ $signal->keyword->keyword }}"</span>
                                    @endif
                                    <span class="text-[color:var(--nx-faint)]">·</span>
                                    <span>{{ $catalog[$signal->signal_type]['label'] ?? $signal->signal_type }}</span>
                                    @if($signal->definition)
                                        <span class="text-[color:var(--nx-faint)]">·</span>
                                        <span class="text-[color:var(--nx-faint)]">{{ $signal->definition->name }}</span>
                                    @endif
                                    @if(!empty($signal->context['impact']))
                                        <span class="text-[color:var(--nx-faint)]">·</span>
                                        <span class="tabular-nums" title="Impact-Score">Impact {{ number_format($signal->context['impact']) }}</span>
                                    @endif
                                </div>
                            </div>

                            @if($signal->status !== 'resolved')
                                <div class="flex shrink-0 items-center gap-3">
                                    @if($signal->status === 'new')
                                        <button wire:click="acknowledge({{ $signal->id }})" class="text-[11px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] transition">Quittieren</button>
                                    @endif
                                    <button wire:click="resolve({{ $signal->id }})" class="text-[11px] font-medium text-[color:var(--nx-success)] hover:opacity-80 transition">Erledigt</button>
                                </div>
                            @endif
                        </div>
                    </x-nx-card>
                @endforeach
            </div>

            @if($hasMore)
                <div class="mt-4 text-center">
                    <x-ui-button variant="secondary" size="sm" wire:click="loadMore">Mehr laden</x-ui-button>
                </div>
            @endif
        @else
            <x-nx-empty icon="heroicon-o-check-circle">
                @if($filterStatus === 'new')
                    Keine offenen Signale. Sauber — oder es fehlen noch aktive Definitionen.
                @else
                    Keine Signale in dieser Ansicht.
                @endif
            </x-nx-empty>
        @endif

    </x-ui-page-container>
</x-ui-page>
