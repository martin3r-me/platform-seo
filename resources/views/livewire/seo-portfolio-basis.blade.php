<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$portfolio->name" icon="heroicon-o-rocket-launch" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wirkungsräume', 'route' => 'seo.portfolios'],
            ['label' => $portfolio->name, 'href' => route('seo.portfolios.show', $portfolio)],
            ['label' => 'Basis'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    @include('seo::partials.wirkungsraum-sidebar', ['portfolio' => $portfolio, 'active' => 'basis', 'health' => $health])

    <x-ui-page-container>
        <div class="mb-1">
            <h1 class="text-lg font-semibold text-[color:var(--nx-text)]">Basis</h1>
            <p class="text-[10px] text-[color:var(--nx-faint)] mt-0.5">{{ $portfolio->name }} · SEO-Ziel + Basis-Cluster je beteiligter Seite — die Grundlage, über der die Themenfelder entstehen.</p>
        </div>

        @include('seo::partials.wirkungsraum-basis', ['basisRows' => $basisRows, 'clusterFlash' => $clusterFlash])
    </x-ui-page-container>
</x-ui-page>
