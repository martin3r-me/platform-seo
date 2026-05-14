<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wettbewerber" icon="heroicon-o-user-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.projects.index'],
            ['label' => $seoProject->name, 'route' => 'seo.projects.show', 'routeParams' => [$seoProject]],
            ['label' => 'Wettbewerber'],
        ]" />
    </x-slot>

    <x-ui-page-container>

        {{-- Navigation Tabs --}}
        <div class="flex items-center gap-1 border-b border-gray-100 mb-6">
            <a href="{{ route('seo.projects.show', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Dashboard</a>
            <a href="{{ route('seo.projects.keywords', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Keywords</a>
            <a href="{{ route('seo.projects.rankings', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Rankings</a>
            <a href="{{ route('seo.projects.competitors', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-indigo-600 border-b-2 border-indigo-600">Wettbewerber</a>
            <a href="{{ route('seo.projects.signals', $seoProject) }}" wire:navigate class="px-4 py-3 text-sm font-medium text-gray-400 hover:text-gray-600">Signale</a>
        </div>

        {{-- Summary --}}
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">Keyword Gaps</div>
                <div class="text-3xl font-light text-gray-900">{{ $gaps['gaps_count'] }}</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">Keywords mit Wettbewerbern</div>
                <div class="text-3xl font-light text-gray-900">{{ $gaps['keywords_with_competitors'] }}</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-5">
                <div class="text-[11px] uppercase tracking-wider text-gray-400 mb-1">Gesamt Keywords</div>
                <div class="text-3xl font-light text-gray-900">{{ $gaps['total_keywords'] }}</div>
            </div>
        </div>

        {{-- Top Competitor Domains --}}
        @if(!empty($gaps['top_competitor_domains']))
            <div class="mb-8">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Top Wettbewerber-Domains</h3>
                <div class="flex items-center gap-3 flex-wrap">
                    @foreach($gaps['top_competitor_domains'] as $domain => $count)
                        <span class="px-3 py-1.5 bg-gray-100 rounded-full text-xs text-gray-600">
                            {{ $domain }} ({{ $count }})
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Gap Table --}}
        @if(!empty($gaps['gaps']))
            <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-gray-400">
                            <th class="px-4 py-3">Keyword</th>
                            <th class="px-4 py-3">Cluster</th>
                            <th class="px-4 py-3 text-right">SV</th>
                            <th class="px-4 py-3 text-right">KD</th>
                            <th class="px-4 py-3 text-right">Unsere Pos.</th>
                            <th class="px-4 py-3 text-right">Beste Competitor Pos.</th>
                            <th class="px-4 py-3 text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($gaps['gaps'] as $gap)
                            <tr class="border-b border-gray-50">
                                <td class="px-4 py-2.5 font-medium text-gray-900">{{ $gap['keyword'] }}</td>
                                <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $gap['cluster'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600">{{ $gap['search_volume'] !== null ? number_format($gap['search_volume']) : '—' }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600">{{ $gap['keyword_difficulty'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600">{{ $gap['our_position'] ?? '—' }}</td>
                                <td class="px-4 py-2.5 text-right text-orange-600 font-medium">{{ $gap['best_competitor_position'] }}</td>
                                <td class="px-4 py-2.5 text-right text-gray-600">{{ $gap['opportunity_score'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-12 text-center text-gray-400">
                Keine Competitor-Gaps gefunden. F&uuml;hre zuerst ein Ranking-Update durch.
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
