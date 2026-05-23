@props(['projectId' => null, 'active' => 'dashboard'])

@include('seo::partials.seo-colors')

@php
    $project = is_object($projectId) ? $projectId : $projectId;
    $tabs = [
        'dashboard' => ['label' => 'Dashboard', 'route' => 'seo.projects.show'],
        'urls' => ['label' => 'URLs', 'route' => 'seo.projects.urls'],
        'keywords' => ['label' => 'Keywords', 'route' => 'seo.projects.keywords'],
        'rankings' => ['label' => 'Rankings', 'route' => 'seo.projects.rankings'],
        'competitors' => ['label' => 'Wettbewerber', 'route' => 'seo.projects.competitors'],
        'cannibalization' => ['label' => 'Kannibalisierung', 'route' => 'seo.projects.cannibalization'],
        'signals' => ['label' => 'Signale', 'route' => 'seo.projects.signals'],
    ];
@endphp

<div class="flex items-center gap-1 border-b border-gray-100 mb-6">
    @foreach($tabs as $key => $tab)
        <a href="{{ route($tab['route'], $project) }}" wire:navigate
           class="px-4 py-3 text-sm font-medium {{ $active === $key ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600' }}">
            {{ $tab['label'] }}
        </a>
    @endforeach
</div>
