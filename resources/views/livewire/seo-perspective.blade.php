<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Perspektive" icon="heroicon-o-rectangle-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => $heading ?: 'Perspektive'],
        ]">
            @if($entityId && \Illuminate\Support\Facades\Route::has('organization.entities.show'))
                <x-ui-button variant="secondary" size="sm" :href="route('organization.entities.show', $entityId)">
                    @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                    <span>Im Org-Baum öffnen</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        {{-- Kopf --}}
        <div class="mb-5">
            <h1 class="text-lg font-semibold text-gray-900">{{ $heading ?: 'Perspektive' }}</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $subtitle }}{{ $kpis['nodes'] ? ' · '.$kpis['nodes'].' Knoten' : '' }}</p>
        </div>

        {{-- Perspektive-Zusammenfassung: KPIs (immer sichtbar) --}}
        @php
            $visHint = null;
            if ($visibilityDelta !== null && $visibilityDelta !== 0) {
                $visHint = ($visibilityDelta > 0 ? '▲ +' : '▼ ').number_format($visibilityDelta).' · 30 T';
            }
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <x-nx-stat label="URLs" :value="number_format($kpis['own'])" :hint="$kpis['competitors'].' Wettbewerber'" />
            <x-nx-stat label="Sichtbarkeit" :value="number_format($kpis['visibility'])" :hint="$visHint" />
            <x-nx-stat label="Keywords" :value="number_format($kpis['keywords'])" />
            <x-nx-stat label="Suchvolumen" :value="number_format($kpis['search_volume'])" />
            <x-nx-stat label="Backlinks" :value="$kpis['backlinks'] === null ? '—' : number_format($kpis['backlinks'])" :hint="$kpis['backlinks'] === null ? 'nicht im Profil' : null" />
            <x-nx-stat label="Traffic (30T)" :value="$kpis['visitors'] === null ? '—' : number_format($kpis['visitors'])" :hint="$kpis['visitors'] === null ? 'nicht im Profil' : null" />
        </div>

        {{-- BISECT: Profil-Block raus --}}

        @php $tabs = ['overview' => 'Übersicht', 'movers' => 'Bewegung', 'urls' => 'URLs', 'cannibalization' => 'Überschneidungen', 'competitors' => 'Wettbewerber', 'recommendations' => 'Empfehlungen', 'clusters' => 'Cluster']; @endphp
        <x-nx-tabs class="mb-6">
            @foreach($tabs as $key => $label)
                <x-nx-tab :active="$tab === $key" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-nx-tab>
            @endforeach
        </x-nx-tabs>


    </x-ui-page-container>
</x-ui-page>
