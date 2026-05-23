@props(['active' => 'dashboard'])

@include('seo::partials.seo-colors')

@php
    $tabs = [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'seo.dashboard'],
        'urls' => ['label' => 'URLs', 'route' => 'seo.urls'],
        'keywords' => ['label' => 'Keywords', 'route' => 'seo.keywords'],
        'rankings' => ['label' => 'Rankings', 'route' => 'seo.rankings'],
        'competitors' => ['label' => 'Wettbewerber', 'route' => 'seo.competitors'],
        'cannibalization' => ['label' => 'Kannibalisierung', 'route' => 'seo.cannibalization'],
        'signals' => ['label' => 'Signale', 'route' => 'seo.signals'],
    ];
@endphp

<div class="flex items-center gap-1 border-b border-gray-100 mb-6">
    @foreach($tabs as $key => $tab)
        <a href="{{ route($tab['route']) }}" wire:navigate
           class="px-4 py-3 text-sm font-medium {{ $active === $key ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
