<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$portfolio->name" icon="heroicon-o-rocket-launch" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wirkungsräume', 'route' => 'seo.portfolios'],
            ['label' => $portfolio->name],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    @include('seo::partials.wirkungsraum-sidebar', ['portfolio' => $portfolio, 'active' => 'dashboard', 'health' => $health])

    <x-ui-page-container>
        @include('seo::partials.wirkungsraum-dashboard', ['agg' => $agg, 'health' => $health, 'trend' => $trend, 'penetration' => $penetration, 'competitors' => $competitors, 'measureInbox' => $measureInbox])
    </x-ui-page-container>
</x-ui-page>
