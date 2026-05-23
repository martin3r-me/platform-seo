<div x-data="{ collapsed: false }">
    <div x-show="!collapsed" class="px-2 mb-1">
        <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">SEO</div>
        @php
            $sidebarItems = [
                ['label' => 'Dashboard', 'route' => 'seo.dashboard', 'icon' => 'heroicon-o-chart-bar-square'],
                ['label' => 'URLs', 'route' => 'seo.urls', 'icon' => 'heroicon-o-globe-alt'],
                ['label' => 'Keywords', 'route' => 'seo.keywords', 'icon' => 'heroicon-o-key'],
                ['label' => 'Rankings', 'route' => 'seo.rankings', 'icon' => 'heroicon-o-chart-bar'],
                ['label' => 'Wettbewerber', 'route' => 'seo.competitors', 'icon' => 'heroicon-o-user-group'],
                ['label' => 'Signale', 'route' => 'seo.signals', 'icon' => 'heroicon-o-bell-alert'],
            ];
        @endphp
        @foreach($sidebarItems as $item)
            <a href="{{ route($item['route']) }}" wire:navigate
               class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
                @svg($item['icon'], 'w-4 h-4')
                <span class="truncate">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>

    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('seo.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="SEO">
                @svg('heroicon-o-magnifying-glass-circle', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
