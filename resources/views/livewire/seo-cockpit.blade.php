<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Agentur" icon="heroicon-o-building-office-2" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Kunden-Portfolio'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-[color:var(--nx-text)]">Kunden-Portfolio</h1>
            <p class="text-[13px] text-[color:var(--nx-muted)] mt-0.5">Welche Kunden sind gesund, welche brauchen dich.</p>
        </div>

        {{-- Portfolio-KPIs --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <x-nx-stat label="Kunden" :value="number_format($totals['customers'])" :hint="$totals['tracked'].' getrackt'" icon="heroicon-o-building-office-2" />
            <x-nx-stat label="Sichtbarkeit" :value="number_format($totals['visibility'])" icon="heroicon-o-eye" />
            <x-nx-stat label="Offene Aufgaben" :value="number_format($totals['recs'])" hint="Empfehlungen" icon="heroicon-o-bolt" :accent="$totals['recs'] > 0 ? 'var(--nx-info)' : null" />
            <x-nx-stat label="Ablage" :value="number_format($ablageCount)" hint="warten auf Zuordnung" icon="heroicon-o-inbox" :href="$ablageCount ? route('seo.perspective.unassigned') : null" :accent="$ablageCount > 0 ? 'var(--nx-warning)' : null" />
        </div>

        {{-- Ablage-CTA --}}
        @if($ablageCount > 0)
            <div class="mb-6">
                <x-nx-callout variant="warning" title="{{ $ablageCount }} URLs warten auf Zuordnung">
                    In der Ablage — einem Kunden zuweisen oder als Wettbewerber klassifizieren.
                    <x-slot name="action"><x-nx-button size="sm" :href="route('seo.perspective.unassigned')">Zur Ablage</x-nx-button></x-slot>
                </x-nx-callout>
            </div>
        @endif

        {{-- Kunden-Karten --}}
        @if(!empty($cards))
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($cards as $card)
                    <a href="{{ route('seo.perspective', $card['id']) }}" wire:navigate class="block">
                        <x-nx-card hover class="h-full">
                            <div class="mb-3 flex items-start justify-between gap-2">
                                <span class="font-medium text-[color:var(--nx-text)] truncate">{{ $card['name'] }}</span>
                                <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" style="background: {{ $card['urls'] > 0 ? 'var(--nx-success)' : 'var(--nx-faint)' }}"></span>
                            </div>

                            @if($card['urls'] > 0)
                                <div class="flex items-baseline gap-2">
                                    <span class="text-2xl font-semibold leading-none tabular-nums text-[color:var(--nx-text)]">{{ number_format($card['visibility']) }}</span>
                                    <span class="text-[10px] uppercase tracking-wide text-[color:var(--nx-faint)]">Sichtbarkeit</span>
                                    @if($card['vis_delta'] !== null && $card['vis_delta'] !== 0)
                                        <span class="text-xs tabular-nums {{ $card['vis_delta'] > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">{{ $card['vis_delta'] > 0 ? '▲ +' : '▼ ' }}{{ number_format($card['vis_delta']) }}</span>
                                    @endif
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <span class="text-xs tabular-nums text-[color:var(--nx-muted)]">{{ number_format($card['urls']) }} URLs · {{ number_format($card['keywords']) }} KW</span>
                                    @if($card['open_recs'] > 0)
                                        <x-nx-badge variant="info">{{ $card['open_recs'] }} offen</x-nx-badge>
                                    @endif
                                </div>
                            @else
                                <div class="py-1 text-xs text-[color:var(--nx-faint)]">Noch nicht getrackt — URLs aufhängen</div>
                            @endif
                        </x-nx-card>
                    </a>
                @endforeach
            </div>
        @else
            <x-nx-empty icon="heroicon-o-building-office-2">Noch keine Kunden. Sobald im Org-Baum Kunden über die Engagement-Ebene modelliert sind, erscheinen sie hier.</x-nx-empty>
        @endif

    </x-ui-page-container>
</x-ui-page>
