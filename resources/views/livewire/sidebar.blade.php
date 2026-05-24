@include('seo::partials.seo-colors')

<x-ui-page-sidebar title="SEO" width="w-56" :defaultOpen="true" storeKey="seoSidebarOpen">
    <div class="p-3 space-y-1">
        @php
            $items = [
                'dashboard' => ['label' => 'Dashboard', 'route' => 'seo.dashboard', 'icon' => 'heroicon-o-chart-bar-square'],
                'lists' => ['label' => 'Listen', 'route' => 'seo.lists', 'icon' => 'heroicon-o-queue-list'],
                'urls' => ['label' => 'URLs', 'route' => 'seo.urls', 'icon' => 'heroicon-o-globe-alt'],
                'keywords' => ['label' => 'Keywords', 'route' => 'seo.keywords', 'icon' => 'heroicon-o-key'],
                'rankings' => ['label' => 'Rankings', 'route' => 'seo.rankings', 'icon' => 'heroicon-o-chart-bar'],
                'competitors' => ['label' => 'Wettbewerber', 'route' => 'seo.competitors', 'icon' => 'heroicon-o-user-group'],
                'cannibalization' => ['label' => 'Kannibalisierung', 'route' => 'seo.cannibalization', 'icon' => 'heroicon-o-exclamation-triangle'],
                'signals' => ['label' => 'Signale', 'route' => 'seo.signals', 'icon' => 'heroicon-o-bell-alert'],
            ];
        @endphp
        @foreach($items as $key => $item)
            <a href="{{ route($item['route']) }}" wire:navigate
               class="flex items-center gap-2.5 px-3 py-2 rounded-md text-sm transition-colors
                      {{ $active === $key
                          ? 'bg-indigo-50 text-indigo-700 font-medium'
                          : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                @svg($item['icon'], 'w-4 h-4 flex-shrink-0')
                <span class="truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</x-ui-page-sidebar>
