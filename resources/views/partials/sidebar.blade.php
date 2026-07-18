<x-ui-page-sidebar title="SEO" width="w-80" :defaultOpen="true">
    <div class="p-6 space-y-6">
        <div>
            <h3 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Navigation</h3>
            <div class="space-y-1">
                @php
                    $items = [
                        'dashboard' => ['label' => 'Dashboard', 'route' => 'seo.dashboard', 'icon' => 'heroicon-o-chart-bar-square'],
                        'recommendations' => ['label' => 'Empfehlungen', 'route' => 'seo.recommendations', 'icon' => 'heroicon-o-light-bulb'],
                        'clusters' => ['label' => 'Cluster', 'route' => 'seo.clusters', 'icon' => 'heroicon-o-squares-2x2'],
                        'lists' => ['label' => 'Listen', 'route' => 'seo.lists', 'icon' => 'heroicon-o-queue-list'],
                        'urls' => ['label' => 'URLs', 'route' => 'seo.urls', 'icon' => 'heroicon-o-globe-alt'],
                    ];
                @endphp
                @foreach($items as $key => $item)
                    <a href="{{ route($item['route']) }}" wire:navigate
                       class="flex items-center gap-2.5 px-3 py-2 rounded-md text-[13px] transition-colors
                              {{ $active === $key
                                  ? 'bg-blue-50 text-[#166EE1] font-medium'
                                  : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">
                        @svg($item['icon'], 'w-4 h-4 flex-shrink-0')
                        <span class="truncate">{{ $item['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-ui-page-sidebar>
