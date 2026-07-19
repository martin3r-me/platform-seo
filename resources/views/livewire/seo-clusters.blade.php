<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Cluster" icon="heroicon-o-squares-2x2" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Cluster'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        {{-- Intro --}}
        <p class="text-[13px] text-gray-500 mb-6">Der Cluster ist die strategische Einheit: systematisch aufgebaut und über die Zeit gemessen. Abdeckung, Sichtbarkeit und Trajektorie zeigen, ob ein Thema gewonnen wird.</p>

        {{-- Sort --}}
        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5 mb-6 w-fit">
            @foreach(['health' => 'Health', 'coverage' => 'Abdeckung', 'visibility' => 'Sichtbarkeit', 'keywords' => 'Keywords'] as $key => $label)
                <button wire:click="setSort('{{ $key }}')"
                        class="px-3 py-1.5 text-[12px] rounded-md transition-colors {{ $sort === $key ? 'bg-white text-gray-900 font-medium shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Cluster List --}}
        <div class="space-y-2">
            @forelse($clusters as $cluster)
                @php
                    $health = $cluster->health_score;
                    $coverage = (float) $cluster->coverage_pct;
                    $healthColor = match(true) {
                        $health === null => 'bg-gray-100 text-gray-400',
                        $health >= 70 => 'bg-green-100 text-green-700',
                        $health >= 40 => 'bg-amber-100 text-amber-700',
                        default => 'bg-red-100 text-red-600',
                    };
                    $barColor = match(true) {
                        $coverage >= 70 => 'bg-green-500',
                        $coverage >= 40 => 'bg-amber-500',
                        default => 'bg-red-400',
                    };
                    $trajectory = $trajectories[$cluster->id] ?? [];
                @endphp
                <div wire:key="cluster-{{ $cluster->id }}" class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center gap-4 flex-wrap">
                        {{-- Name --}}
                        <div class="flex items-center gap-2.5 min-w-[180px] flex-1">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $cluster->color ?: '#94a3b8' }}"></span>
                            <div class="min-w-0">
                                <a href="{{ route('seo.clusters.show', $cluster) }}" wire:navigate class="font-medium text-[13px] text-gray-900 hover:text-indigo-600 truncate block">{{ $cluster->name }}</a>
                                <div class="text-[11px] text-gray-400">{{ $cluster->keyword_count }} Keywords · {{ $cluster->covered_keywords }} abgedeckt</div>
                            </div>
                        </div>

                        {{-- Coverage --}}
                        <div class="w-[130px]">
                            <div class="flex items-center justify-between text-[11px] mb-1">
                                <span class="text-gray-400 uppercase tracking-wide">Abdeckung</span>
                                <span class="font-medium text-gray-700 tabular-nums">{{ number_format($coverage, 0) }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                <div class="h-full rounded-full {{ $barColor }}" style="width: {{ min(100, $coverage) }}%"></div>
                            </div>
                        </div>

                        {{-- Top positions --}}
                        <div class="text-center w-[64px]">
                            <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">Top 3/10</div>
                            <div class="text-[13px] font-medium text-gray-700 tabular-nums">{{ $cluster->top3_count }}/{{ $cluster->top10_count }}</div>
                        </div>

                        {{-- Visibility --}}
                        <div class="text-center w-[80px]">
                            <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">Sichtbarkeit</div>
                            <div class="text-[13px] font-medium text-gray-700 tabular-nums">{{ number_format($cluster->visibility, 0) }}</div>
                        </div>

                        {{-- Traffic --}}
                        <div class="text-center w-[92px]">
                            <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">Traffic (30T)</div>
                            <div class="text-[13px] font-medium text-gray-700 tabular-nums">{{ number_format($cluster->clicks_30d) }} · {{ number_format($cluster->visitors_30d) }}</div>
                        </div>

                        {{-- Trajectory --}}
                        <div class="w-[120px]">
                            @if(count($trajectory) > 1)
                                @include('seo::partials.sparkline', ['data' => $trajectory, 'color' => '#0e6e78', 'height' => 34, 'type' => 'area'])
                            @else
                                <div class="text-[11px] text-gray-300 text-center">—</div>
                            @endif
                        </div>

                        {{-- Health --}}
                        <div class="text-center w-[70px]">
                            <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-0.5">Health</div>
                            <span class="inline-block text-[13px] font-semibold px-2 py-0.5 rounded {{ $healthColor }} tabular-nums">{{ $health ?? '—' }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="py-16 text-center">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        @svg('heroicon-o-squares-2x2', 'w-5 h-5 text-gray-400')
                    </div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Keine Cluster</p>
                    <p class="text-xs text-gray-400">Sobald Keywords geclustert und gemessen sind (seo:snapshot-clusters), erscheinen hier Abdeckung, Health und Trajektorie.</p>
                </div>
            @endforelse
        </div>

        @if($hasMore)
            <div x-data x-intersect="$wire.loadMore()" class="py-4 text-center">
                <span wire:loading.delay wire:target="loadMore" class="text-[12px] text-gray-400">Laden...</span>
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
