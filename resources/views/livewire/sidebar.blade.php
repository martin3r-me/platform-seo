<div x-data="{ collapsed: false }">
    <div x-show="!collapsed" class="px-2 mb-1">
        <div class="px-2 py-1.5 text-[10px] uppercase tracking-widest text-gray-500 font-medium">Projekte</div>
        @foreach($projects as $project)
            <a href="{{ route('seo.projects.show', $project) }}" wire:navigate wire:key="project-{{ $project->id }}"
               class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-300 hover:bg-[#2C3135] hover:text-white transition-colors">
                @svg('heroicon-o-magnifying-glass-circle', 'w-4 h-4')
                <span class="truncate">{{ $project->name }}</span>
            </a>
        @endforeach
        <a href="{{ route('seo.projects.index') }}" wire:navigate class="flex items-center gap-2.5 px-3 py-1.5 rounded-md text-[13px] text-gray-500 hover:bg-[#2C3135] hover:text-gray-300 transition-colors">
            @svg('heroicon-o-plus', 'w-4 h-4')
            <span>Neues Projekt</span>
        </a>
    </div>

    <div x-show="collapsed" class="px-2 py-2 border-b border-[#2C3135]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('seo.projects.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-[#2C3135] transition-colors" title="SEO Projekte">
                @svg('heroicon-o-magnifying-glass-circle', 'w-5 h-5')
            </a>
        </div>
    </div>
</div>
