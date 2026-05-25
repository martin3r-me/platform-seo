<div>
    <div x-show="!collapsed" class="px-3 pt-3 pb-2 border-b border-[#2C3135] mb-2">
        <span class="text-[10px] uppercase tracking-widest text-gray-500 font-medium">SEO</span>
    </div>

    <div x-show="!collapsed" class="px-2 mb-1">
        <a href="{{ route('seo.dashboard') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-chart-bar-square', 'w-4 h-4')
            <span>Dashboard</span>
        </a>
        <a href="{{ route('seo.lists') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-queue-list', 'w-4 h-4')
            <span>Listen</span>
        </a>
        <a href="{{ route('seo.urls') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
            @svg('heroicon-o-globe-alt', 'w-4 h-4')
            <span>URLs</span>
        </a>
    </div>

    {{-- Collapsed View --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('seo.dashboard') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Dashboard">
                @svg('heroicon-o-chart-bar-square', 'w-5 h-5')
            </a>
            <a href="{{ route('seo.lists') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="Listen">
                @svg('heroicon-o-queue-list', 'w-5 h-5')
            </a>
            <a href="{{ route('seo.urls') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="URLs">
                @svg('heroicon-o-globe-alt', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
