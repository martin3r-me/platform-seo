<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wettbewerber" icon="heroicon-o-user-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Listen', 'route' => 'seo.lists'],
            ['label' => $seoUrlList->name, 'href' => route('seo.lists.show', $seoUrlList)],
            ['label' => 'Wettbewerber'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'lists'])
    </x-slot>

    <x-ui-page-container>

        {{-- Sub-Navigation --}}
        <div class="flex items-center gap-1 border-b border-gray-200 mb-6">
            <a href="{{ route('seo.lists.show', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">Übersicht</a>
            <a href="{{ route('seo.lists.competitors', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-[#166EE1] border-b-2 border-[#166EE1]">Wettbewerber</a>
            <a href="{{ route('seo.lists.cannibalization', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">Kannibalisierung</a>
            <a href="{{ route('seo.lists.signals', $seoUrlList) }}" wire:navigate class="px-4 py-3 text-[13px] font-medium text-gray-500 hover:text-gray-700 transition-colors">Signale</a>
        </div>

        {{-- Intro --}}
        <p class="text-[13px] text-gray-500 mb-6">Domains, die für dieselben Keywords ranken wie du. Der Keyword-Gap zeigt Begriffe, bei denen Wettbewerber sichtbar sind, du aber nicht — oder umgekehrt. Nutze diese Daten, um Content-Lücken zu finden.</p>

        {{-- Summary Stats --}}
        <div class="grid grid-cols-3 gap-4 mb-8">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-amber-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Keyword Gaps</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $gaps['gaps_count'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Keywords, bei denen du nachlegen kannst</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-users', 'w-4 h-4 text-blue-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Mit Wettbewerbern</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $gaps['keywords_with_competitors'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Keywords mit Konkurrenz in den SERPs</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="flex items-center gap-2 mb-1">
                    @svg('heroicon-o-key', 'w-4 h-4 text-gray-500')
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Gesamt</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $gaps['total_keywords'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">Alle Keywords in dieser Liste</div>
            </div>
        </div>

        {{-- Domain Overview --}}
        @if($competitorDomains->isNotEmpty())
            <div class="mb-8">
                <div class="flex items-center justify-between mb-3">
                    <div>
                        <h3 class="text-[13px] font-semibold text-gray-900">Wettbewerber-Domains</h3>
                        <p class="text-[11px] text-gray-400 mt-0.5">Klicke auf eine Domain, um nur deren URLs und Keyword-Überschneidungen zu sehen.</p>
                    </div>
                    @if($filterDomain)
                        <button wire:click="setDomainFilter(null)" class="text-[11px] text-indigo-600 hover:underline">Filter zurücksetzen</button>
                    @endif
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($competitorDomains as $domain)
                        <button wire:click="setDomainFilter('{{ $domain->domain }}')"
                                class="bg-white rounded-lg border {{ $filterDomain === $domain->domain ? 'border-indigo-300 ring-2 ring-indigo-100' : 'border-gray-200 hover:border-indigo-200 hover:shadow-sm' }} p-4 text-left transition-all">
                            <div class="font-semibold text-[13px] text-gray-900 mb-3">{{ $domain->domain }}</div>
                            <div class="grid grid-cols-3 gap-2 text-center">
                                <div class="bg-gray-50 rounded-md px-2 py-1.5">
                                    <div class="text-sm font-semibold text-gray-800 tabular-nums">{{ $domain->url_count }}</div>
                                    <div class="text-[10px] text-gray-400 uppercase">URLs</div>
                                </div>
                                <div class="bg-gray-50 rounded-md px-2 py-1.5">
                                    <div class="text-sm font-semibold text-gray-800 tabular-nums">{{ $domain->total_keywords }}</div>
                                    <div class="text-[10px] text-gray-400 uppercase">KWs</div>
                                </div>
                                <div class="bg-gray-50 rounded-md px-2 py-1.5">
                                    <div class="text-sm font-semibold text-gray-800 tabular-nums">{{ number_format($domain->avg_visibility, 1) }}</div>
                                    <div class="text-[10px] text-gray-400 uppercase">Sicht.</div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Competitor URLs Table --}}
        @if($competitorUrls->isNotEmpty())
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden mb-8">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-[13px] font-semibold text-gray-900">
                        Wettbewerber-URLs
                        @if($filterDomain)
                            <span class="text-gray-400 font-normal">({{ $filterDomain }})</span>
                        @endif
                    </h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Konkurrenzseiten, die für ähnliche Keywords ranken. Analysiere deren Inhalte für Optimierungsideen.</p>
                </div>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                            <th class="px-5 py-2.5 text-left">URL</th>
                            <th class="px-4 py-2.5 text-right">Keywords</th>
                            <th class="px-4 py-2.5 text-right">SV</th>
                            <th class="px-4 py-2.5 text-right">Sichtbarkeit</th>
                            <th class="px-4 py-2.5 text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($competitorUrls as $url)
                            <tr wire:key="comp-url-{{ $url->id }}" class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-2.5">
                                    <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-xs font-medium">{{ $url->path ?: '/' }}</a>
                                    <span class="text-[10px] text-gray-400">{{ $url->domain }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right text-gray-600 tabular-nums">{{ $url->keyword_count }}</td>
                                <td class="px-4 py-2.5 text-right">@include('seo::partials.sv-badge', ['volume' => $url->total_search_volume])</td>
                                <td class="px-4 py-2.5 text-right font-semibold text-gray-900 tabular-nums">{{ number_format($url->visibility_score, 1) }}</td>
                                <td class="px-4 py-2.5 text-center">@include('seo::partials.url-status-badge', ['status' => $url->status, 'httpStatus' => $url->http_status])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($hasMore)
                <div x-data x-intersect="$wire.loadMore()" class="py-4 text-center">
                    <span wire:loading.delay wire:target="loadMore" class="text-[12px] text-gray-400">Laden...</span>
                </div>
            @endif
        @endif

        {{-- Gap Table --}}
        @if(!empty($gaps['gaps']))
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-[13px] font-semibold text-gray-900">Keyword Gaps</h3>
                    <p class="text-[11px] text-gray-400 mt-0.5">Keywords, bei denen dein Wettbewerber besser rankt als du. Hoher Opportunity-Score = hohes Suchvolumen bei niedrigem eigenen Ranking.</p>
                </div>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                            <th class="px-5 py-2.5 text-left">Keyword</th>
                            <th class="px-4 py-2.5 text-right">SV</th>
                            <th class="px-4 py-2.5 text-right">KD</th>
                            <th class="px-4 py-2.5 text-right">Deine Pos.</th>
                            <th class="px-4 py-2.5 text-right">Wettb. Pos.</th>
                            <th class="px-4 py-2.5 text-right">Score</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($gaps['gaps'] as $gap)
                            <tr class="hover:bg-gray-50 transition-colors">
                                <td class="px-5 py-2.5 font-medium text-gray-900">{{ $gap['keyword'] }}</td>
                                <td class="px-4 py-2.5 text-right">@include('seo::partials.sv-badge', ['volume' => $gap['search_volume']])</td>
                                <td class="px-4 py-2.5 text-right">@include('seo::partials.kd-badge', ['value' => $gap['keyword_difficulty']])</td>
                                <td class="px-4 py-2.5 text-right">@include('seo::partials.position-badge', ['position' => $gap['our_position'], 'change' => null])</td>
                                <td class="px-4 py-2.5 text-right">@include('seo::partials.position-badge', ['position' => $gap['best_competitor_position'], 'change' => null])</td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="inline-flex items-center justify-center w-8 h-5 rounded text-[11px] font-bold {{ $gap['opportunity_score'] >= 70 ? 'bg-green-100 text-green-700' : ($gap['opportunity_score'] >= 40 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600') }}">
                                        {{ $gap['opportunity_score'] }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="py-16 text-center">
                <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    @svg('heroicon-o-user-group', 'w-5 h-5 text-gray-400')
                </div>
                <p class="text-sm text-gray-500 font-medium mb-1">Keine Keyword-Gaps gefunden</p>
                <p class="text-xs text-gray-400">Führe zuerst ein Ranking-Update durch, damit Wettbewerber-Daten gesammelt werden.</p>
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
