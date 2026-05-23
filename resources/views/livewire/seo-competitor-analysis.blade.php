<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Wettbewerber" icon="heroicon-o-user-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wettbewerber'],
        ]" />
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.project-tabs', ['active' => 'competitors'])

        {{-- Summary Stats --}}
        <x-ui-stats-grid :cols="3">
            <x-ui-dashboard-tile title="Keyword Gaps" :count="$gaps['gaps_count']" icon="exclamation-triangle" variant="warning" />
            <x-ui-dashboard-tile title="Keywords mit Wettbewerbern" :count="$gaps['keywords_with_competitors']" icon="users" variant="info" />
            <x-ui-dashboard-tile title="Gesamt Keywords" :count="$gaps['total_keywords']" icon="key" variant="neutral" />
        </x-ui-stats-grid>

        {{-- Domain Overview --}}
        @if($competitorDomains->isNotEmpty())
            <div>
                <h3 class="text-sm font-medium text-gray-700 mb-3">Wettbewerber-Domains</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($competitorDomains as $domain)
                        <button wire:click="setDomainFilter('{{ $domain->domain }}')"
                                class="bg-white rounded-xl border {{ $filterDomain === $domain->domain ? 'border-indigo-300 ring-1 ring-indigo-200' : 'border-gray-100 hover:border-gray-200' }} p-4 text-left transition-all">
                            <div class="font-medium text-gray-900 text-sm">{{ $domain->domain }}</div>
                            <div class="grid grid-cols-3 gap-2 mt-3 text-center">
                                <div>
                                    <div class="text-lg font-semibold text-gray-900">{{ $domain->url_count }}</div>
                                    <div class="text-[10px] uppercase tracking-wider text-gray-400">URLs</div>
                                </div>
                                <div>
                                    <div class="text-lg font-semibold text-gray-900">{{ $domain->total_keywords }}</div>
                                    <div class="text-[10px] uppercase tracking-wider text-gray-400">Keywords</div>
                                </div>
                                <div>
                                    <div class="text-lg font-semibold text-gray-900">{{ number_format($domain->avg_visibility, 1) }}</div>
                                    <div class="text-[10px] uppercase tracking-wider text-gray-400">Ø Sichtb.</div>
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
                @if($filterDomain)
                    <button wire:click="setDomainFilter(null)" class="mt-2 text-xs text-indigo-600 hover:underline">Filter zurücksetzen</button>
                @endif
            </div>
        @endif

        {{-- Competitor URLs Table --}}
        @if($competitorUrls->isNotEmpty())
            <div>
                <h3 class="text-sm font-medium text-gray-700 mb-3">
                    Wettbewerber-URLs
                    @if($filterDomain)
                        <span class="text-gray-400 font-normal">({{ $filterDomain }})</span>
                    @endif
                </h3>
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-400">
                                <th class="px-4 py-3">URL</th>
                                <th class="px-4 py-3 text-right">Keywords</th>
                                <th class="px-4 py-3 text-right">SV</th>
                                <th class="px-4 py-3 text-right">Sichtbarkeit</th>
                                <th class="px-4 py-3 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($competitorUrls as $url)
                                <tr wire:key="comp-url-{{ $url->id }}" class="border-b border-gray-50 hover:bg-gray-50/50">
                                    <td class="px-4 py-2.5">
                                        <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-indigo-600 hover:underline truncate block max-w-xs">
                                            {{ $url->path ?: '/' }}
                                        </a>
                                        <span class="text-xs text-gray-400">{{ $url->domain }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600">{{ $url->keyword_count }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        @include('seo::partials.sv-badge', ['volume' => $url->total_search_volume])
                                    </td>
                                    <td class="px-4 py-2.5 text-right font-medium text-gray-900">{{ number_format($url->visibility_score, 1) }}</td>
                                    <td class="px-4 py-2.5 text-center">
                                        @include('seo::partials.url-status-badge', ['status' => $url->status, 'httpStatus' => $url->http_status])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $competitorUrls->links() }}</div>
            </div>
        @endif

        {{-- Gap Table --}}
        @if(!empty($gaps['gaps']))
            <div>
                <h3 class="text-sm font-medium text-gray-700 mb-3">Keyword Gaps</h3>
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 text-left text-gray-400">
                                <th class="px-4 py-3">Keyword</th>
                                <th class="px-4 py-3 text-right">SV</th>
                                <th class="px-4 py-3 text-right">KD</th>
                                <th class="px-4 py-3 text-right">Unsere Pos.</th>
                                <th class="px-4 py-3 text-right">Wettbewerber Pos.</th>
                                <th class="px-4 py-3 text-right">Score</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($gaps['gaps'] as $gap)
                                <tr class="border-b border-gray-50">
                                    <td class="px-4 py-2.5 font-medium text-gray-900">{{ $gap['keyword'] }}</td>
                                    <td class="px-4 py-2.5 text-right">
                                        @include('seo::partials.sv-badge', ['volume' => $gap['search_volume']])
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        @include('seo::partials.kd-badge', ['value' => $gap['keyword_difficulty']])
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        @include('seo::partials.position-badge', ['position' => $gap['our_position'], 'change' => null])
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        @include('seo::partials.position-badge', ['position' => $gap['best_competitor_position'], 'change' => null])
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600 font-medium">{{ $gap['opportunity_score'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <div class="py-12 text-center text-gray-400">
                Keine Competitor-Gaps gefunden. Führe zuerst ein Ranking-Update durch.
            </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
