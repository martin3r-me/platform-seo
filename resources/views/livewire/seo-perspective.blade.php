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

        {{-- BISECT: nur 1-38, Rest raus --}}

    </x-ui-page-container>
</x-ui-page>
