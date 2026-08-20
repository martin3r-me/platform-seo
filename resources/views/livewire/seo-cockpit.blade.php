<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Agentur" icon="heroicon-o-building-office-2" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Dashboard'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-[color:var(--nx-text)]">Dashboard</h1>
            <p class="text-[13px] text-[color:var(--nx-muted)] mt-0.5">Wo stehen die Wirkungsräume, welche Kunden brauchen dich — und was ist als Nächstes zu tun.</p>
        </div>

        {{-- KPIs --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <x-nx-stat label="Wirkungsräume" :value="number_format($totals['wirkungsraeume'])" hint="Steuer-Scopes" icon="heroicon-o-rocket-launch" :href="route('seo.portfolios')" />
            <x-nx-stat label="Kunden" :value="number_format($totals['customers'])" :hint="$totals['tracked'].' getrackt'" icon="heroicon-o-building-office-2" />
            <x-nx-stat label="Sichtbarkeit" :value="number_format($totals['visibility'])" icon="heroicon-o-eye" />
            <x-nx-stat label="Verwaist" :value="number_format($ablageCount)" hint="ohne Zuhause" icon="heroicon-o-inbox" :href="$ablageCount ? route('seo.perspective.unassigned') : null" :accent="$ablageCount > 0 ? 'var(--nx-warning)' : null" />
        </div>

        {{-- Verwaiste URLs — eigene Seiten ohne jedes Zuhause (kein Wirkungsraum, keine Liste, kein Modul, kein Org-Knoten). --}}
        @if($ablageCount > 0)
            <div class="mb-6">
                <x-nx-callout variant="warning" title="{{ $ablageCount }} verwaiste {{ $ablageCount === 1 ? 'URL' : 'URLs' }}">
                    Eigene Seiten ohne Zuhause — in keinem Wirkungsraum, keiner Liste, keinem Modul, an keinem Org-Knoten. Zuordnen oder aussortieren.
                    <x-slot name="action"><x-nx-button size="sm" :href="route('seo.perspective.unassigned')">Ansehen</x-nx-button></x-slot>
                </x-nx-callout>
            </div>
        @endif

        {{-- ============================================================= --}}
        {{-- Wirkungsräume — der Handlungsort. Reifegrad-Phase + nächster Zug. --}}
        {{-- ============================================================= --}}
        <div class="mt-2 mb-3 flex items-end justify-between gap-4">
            <div>
                <h2 class="text-sm font-semibold text-[color:var(--nx-text)]">Wirkungsräume</h2>
                <p class="text-[13px] text-[color:var(--nx-muted)] mt-0.5">Steuer-Scopes — Reifegrad und der nächste Zug (das erste offene Gate im Trichter).</p>
            </div>
            <a href="{{ route('seo.portfolios') }}" wire:navigate class="shrink-0 text-[12px] font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle →</a>
        </div>

        @if(!empty($wirkungsraeume))
            <x-nx-card flush class="mb-10">
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Wirkungsraum</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Phase</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Sichtbarkeit</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">URLs</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Ordnung</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Was zu tun</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach($wirkungsraeume as $wr)
                            @php($__phaseTone = ['measure' => 'var(--nx-info)', 'organize' => 'var(--nx-warning)', 'distribute' => 'var(--nx-info)', 'act' => 'var(--nx-info)', 'impact' => 'var(--nx-success)'][$wr['phase_key']] ?? 'var(--nx-faint)')
                            <x-nx-table-row>
                                <x-nx-table-cell>
                                    <a href="{{ route('seo.portfolios.show', $wr['id']) }}" wire:navigate class="block truncate max-w-[220px] font-medium text-[color:var(--nx-text)] hover:underline">{{ $wr['name'] }}</a>
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    <span class="inline-flex items-center gap-1.5 text-[12px] text-[color:var(--nx-muted)]">
                                        <span class="h-1.5 w-1.5 rounded-full" style="background: {{ $__phaseTone }}"></span>{{ $wr['phase'] }}
                                    </span>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right"><span class="tabular-nums font-medium text-[color:var(--nx-text)]">{{ number_format($wr['visibility']) }}</span></x-nx-table-cell>
                                <x-nx-table-cell align="right"><span class="tabular-nums text-[color:var(--nx-muted)]">{{ number_format($wr['urls']) }}</span></x-nx-table-cell>
                                <x-nx-table-cell align="right"><span class="tabular-nums {{ $wr['ordnung'] >= 70 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-muted)]' }}">{{ $wr['ordnung'] }}%</span></x-nx-table-cell>
                                <x-nx-table-cell>
                                    <a href="{{ route('seo.portfolios.show', $wr['id']) }}" wire:navigate class="block rounded-md px-2.5 py-1.5 border-l-2 hover:brightness-95" style="border-color: {{ $__phaseTone }}; background: color-mix(in srgb, {{ $__phaseTone }} 8%, transparent)" title="{{ $wr['reason'] }}">
                                        <span class="text-[12px] leading-snug font-medium text-[color:var(--nx-text)]">{{ $wr['action'] }}</span>
                                    </a>
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            </x-nx-card>
        @else
            <x-nx-empty icon="heroicon-o-rocket-launch" class="mb-10">
                Noch kein Wirkungsraum. <a href="{{ route('seo.portfolios') }}" wire:navigate class="underline">Steuer-Scope anlegen</a> — ein Verbund kontrollierter URLs mit einem Ziel.
            </x-nx-empty>
        @endif

        {{-- ============================================================= --}}
        {{-- Kunden — Gesundheit je Kunde, mit dem größten Hebel als Trigger. --}}
        {{-- ============================================================= --}}
        <div class="mt-2 mb-3">
            <h2 class="text-sm font-semibold text-[color:var(--nx-text)]">Kunden</h2>
            <p class="text-[13px] text-[color:var(--nx-muted)] mt-0.5">Welche Kunden sind gesund, welche brauchen dich — mit dem größten Hebel je Kunde.</p>
        </div>

        @if(!empty($cards))
            <x-nx-card flush class="mb-10">
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Kunde</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Sichtbarkeit</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">URLs · KW</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Offen</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Was zu tun</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach($cards as $card)
                            @php($__dot = ['live' => 'var(--nx-success)', 'building' => 'var(--nx-info)', 'untracked' => 'var(--nx-faint)'][$card['state'] ?? 'untracked'] ?? 'var(--nx-faint)')
                            @php($__stateTitle = ['live' => 'Rankt', 'building' => 'Im Aufbau — noch keine Sichtbarkeit', 'untracked' => 'Nicht getrackt'][$card['state'] ?? 'untracked'] ?? '')
                            @php($__ins = $card['insight'] ?? null)
                            @php($__tone = ['danger' => 'var(--nx-danger)', 'warning' => 'var(--nx-warning)', 'success' => 'var(--nx-success)', 'info' => 'var(--nx-info)', 'muted' => 'var(--nx-faint)'][$__ins['tone'] ?? 'muted'] ?? 'var(--nx-faint)')
                            <x-nx-table-row>
                                <x-nx-table-cell>
                                    <span class="flex items-center gap-2">
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $__dot }}" title="{{ $__stateTitle }}"></span>
                                        <a href="{{ route('seo.perspective', $card['id']) }}" wire:navigate class="block truncate max-w-[220px] font-medium text-[color:var(--nx-text)] hover:underline">{{ $card['name'] }}</a>
                                        @if(($card['brands'] ?? 0) > 0)
                                            <span class="text-[10px] uppercase tracking-wide text-[color:var(--nx-faint)] shrink-0">{{ $card['brands'] }} {{ $card['brands'] === 1 ? 'Marke' : 'Marken' }}</span>
                                        @endif
                                    </span>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right">
                                    <span class="inline-flex items-baseline gap-1.5">
                                        <span class="tabular-nums font-medium text-[color:var(--nx-text)]">{{ number_format($card['visibility']) }}</span>
                                        @if($card['vis_delta'] !== null && $card['vis_delta'] !== 0)
                                            <span class="text-[11px] tabular-nums {{ $card['vis_delta'] > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">{{ $card['vis_delta'] > 0 ? '▲+' : '▼' }}{{ number_format($card['vis_delta']) }}</span>
                                        @endif
                                    </span>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right"><span class="tabular-nums text-[color:var(--nx-muted)]">{{ number_format($card['urls']) }} · {{ number_format($card['keywords']) }}</span></x-nx-table-cell>
                                <x-nx-table-cell align="right">
                                    @if($card['open_recs'] > 0)<x-nx-badge variant="info">{{ $card['open_recs'] }}</x-nx-badge>@else<span class="text-[color:var(--nx-faint)]">—</span>@endif
                                </x-nx-table-cell>
                                <x-nx-table-cell>
                                    @if($__ins)
                                        <a href="{{ route('seo.perspective', $card['id']) }}" wire:navigate class="block rounded-md px-2.5 py-1.5 border-l-2 hover:brightness-95" style="border-color: {{ $__tone }}; background: color-mix(in srgb, {{ $__tone }} 8%, transparent)">
                                            <span class="text-[12px] leading-snug font-medium text-[color:var(--nx-text)] line-clamp-1">{{ $__ins['text'] }}</span>
                                        </a>
                                    @else
                                        <a href="{{ route('seo.perspective', $card['id']) }}" wire:navigate class="block rounded-md px-2.5 py-1.5 border-l-2 border-[color:var(--nx-warning)] hover:brightness-95" style="background: color-mix(in srgb, var(--nx-warning) 8%, transparent)">
                                            <span class="text-[12px] leading-snug font-medium text-[color:var(--nx-text)]">URLs aufhängen — noch nicht getrackt</span>
                                        </a>
                                    @endif
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            </x-nx-card>
        @else
            <x-nx-empty icon="heroicon-o-building-office-2" class="mb-10">Noch keine Kunden. Sobald im Org-Baum Kunden über die Engagement-Ebene modelliert sind, erscheinen sie hier.</x-nx-empty>
        @endif

        {{-- ============================================================= --}}
        {{-- Listen · Markt & Themen — Kannibalisierung quer zum Org-Baum. --}}
        {{-- ============================================================= --}}
        @if(!empty($lists))
            <div class="mt-2 mb-3">
                <h2 class="text-sm font-semibold text-[color:var(--nx-text)]">Listen · Markt & Themen</h2>
                <p class="text-[13px] text-[color:var(--nx-muted)] mt-0.5">Quer zum Org-Baum — wo mehrere eigene Seiten dasselbe Keyword bespielen (Kannibalisierung).</p>
            </div>
            <x-nx-card flush>
                <x-nx-table>
                    <x-nx-table-header>
                        <x-nx-table-header-cell>Liste</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">URLs</x-nx-table-header-cell>
                        <x-nx-table-header-cell align="right">Überschneidungen</x-nx-table-header-cell>
                        <x-nx-table-header-cell>Was zu tun</x-nx-table-header-cell>
                    </x-nx-table-header>
                    <x-nx-table-body>
                        @foreach($lists as $list)
                            @php($__hasOverlap = $list['overlaps'] > 0)
                            @php($__href = $__hasOverlap ? route('seo.lists.cannibalization', $list['id']) : route('seo.lists.show', $list['id']))
                            <x-nx-table-row>
                                <x-nx-table-cell>
                                    <a href="{{ $__href }}" wire:navigate class="block truncate max-w-[220px] font-medium text-[color:var(--nx-text)] hover:underline">{{ $list['name'] }}</a>
                                </x-nx-table-cell>
                                <x-nx-table-cell align="right"><span class="tabular-nums text-[color:var(--nx-muted)]">{{ number_format($list['urls']) }}</span></x-nx-table-cell>
                                <x-nx-table-cell align="right"><span class="tabular-nums font-medium {{ $__hasOverlap ? 'text-[color:var(--nx-warning)]' : 'text-[color:var(--nx-faint)]' }}">{{ $__hasOverlap ? number_format($list['overlaps']) : '0' }}</span></x-nx-table-cell>
                                <x-nx-table-cell>
                                    @if($__hasOverlap)
                                        <a href="{{ $__href }}" wire:navigate class="block rounded-md px-2.5 py-1.5 border-l-2 border-[color:var(--nx-warning)] hover:brightness-95" style="background: color-mix(in srgb, var(--nx-warning) 8%, transparent)">
                                            <span class="text-[12px] leading-snug font-medium text-[color:var(--nx-text)]">Kannibalisierung prüfen — Owner zuweisen</span>
                                        </a>
                                    @else
                                        <span class="text-[12px] text-[color:var(--nx-faint)]">Sauber aufgestellt</span>
                                    @endif
                                </x-nx-table-cell>
                            </x-nx-table-row>
                        @endforeach
                    </x-nx-table-body>
                </x-nx-table>
            </x-nx-card>
        @endif

    </x-ui-page-container>
</x-ui-page>
