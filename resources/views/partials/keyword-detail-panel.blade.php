@props(['keyword', 'urls', 'positionHistory'])

@php
    $kdColor = match(true) {
        ($keyword->keyword_difficulty ?? 0) <= 14 => '#2ecc71',
        ($keyword->keyword_difficulty ?? 0) <= 29 => '#48c774',
        ($keyword->keyword_difficulty ?? 0) <= 39 => '#a3cb38',
        ($keyword->keyword_difficulty ?? 0) <= 54 => '#f9ca24',
        ($keyword->keyword_difficulty ?? 0) <= 69 => '#f39c12',
        ($keyword->keyword_difficulty ?? 0) <= 84 => '#e74c3c',
        default => '#c0392b',
    };
    $kdLabel = match(true) {
        ($keyword->keyword_difficulty ?? 0) <= 14 => 'Easy',
        ($keyword->keyword_difficulty ?? 0) <= 29 => 'Still easy',
        ($keyword->keyword_difficulty ?? 0) <= 39 => 'Possible',
        ($keyword->keyword_difficulty ?? 0) <= 54 => 'Still possible',
        ($keyword->keyword_difficulty ?? 0) <= 69 => 'Hard',
        ($keyword->keyword_difficulty ?? 0) <= 84 => 'Very hard',
        default => "Don't do it",
    };
@endphp

<div class="divide-y divide-gray-100">

    {{-- KD Score Section --}}
    <div class="p-5">
        <div class="flex items-center gap-5">
            {{-- Big KD Gauge --}}
            <div class="shrink-0" wire:key="gauge-{{ $keyword->id }}">
                @include('seo::partials.score-gauge', ['value' => $keyword->keyword_difficulty ?? 0, 'label' => '', 'size' => 'lg'])
            </div>
            {{-- Difficulty Label + Quick Stats --}}
            <div class="flex-1 min-w-0">
                <div class="text-xs font-semibold uppercase tracking-wider mb-1" style="color: {{ $kdColor }}">{{ $kdLabel }}</div>
                <div class="text-[11px] text-gray-400 mb-3">Keyword SEO Difficulty</div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-gray-50 rounded-md px-3 py-2">
                        <div class="text-[10px] text-gray-400 uppercase">Search Vol.</div>
                        <div class="text-sm font-semibold text-gray-800">{{ number_format($keyword->search_volume ?? 0) }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-md px-3 py-2">
                        <div class="text-[10px] text-gray-400 uppercase">CPC</div>
                        <div class="text-sm font-semibold text-gray-800">{{ $keyword->cpc_euro !== null ? number_format($keyword->cpc_euro, 2) . ' €' : '—' }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-md px-3 py-2">
                        <div class="text-[10px] text-gray-400 uppercase">PPC</div>
                        <div class="text-sm font-semibold text-gray-800">{{ $keyword->competition !== null ? round($keyword->competition * 100) : '—' }}</div>
                    </div>
                    <div class="bg-gray-50 rounded-md px-3 py-2">
                        <div class="text-[10px] text-gray-400 uppercase">Intent</div>
                        <div class="text-sm font-semibold text-gray-800">{{ $keyword->search_intent ? ucfirst($keyword->search_intent) : '—' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Search Volume Trend (Monthly) --}}
    <div class="p-5">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Monthly Searches</span>
            @if($keyword->monthly_volumes && count($keyword->monthly_volumes) >= 6)
                <span class="text-[10px] text-gray-400">{{ count($keyword->monthly_volumes) }} Monate</span>
            @endif
        </div>
        @if($keyword->monthly_volumes && count($keyword->monthly_volumes) >= 6)
            <div wire:key="sv-trend-{{ $keyword->id }}" wire:ignore
                 x-data x-init="$nextTick(() => {
                    if (typeof ApexCharts !== 'undefined') {
                        const months = ['Jan','Feb','Mär','Apr','Mai','Jun','Jul','Aug','Sep','Okt','Nov','Dez'];
                        const data = {{ json_encode(array_values($keyword->monthly_volumes)) }};
                        new ApexCharts($el, {
                            chart: { type: 'bar', height: 160, toolbar: { show: false },
                                fontFamily: 'inherit', parentHeightOffset: 0 },
                            series: [{ name: 'Suchvolumen', data: data }],
                            colors: ['#6366f1'],
                            plotOptions: { bar: { borderRadius: 3, columnWidth: '55%' } },
                            grid: { show: true, borderColor: '#f3f4f6', strokeDashArray: 3,
                                padding: { left: 0, right: 0, top: -10, bottom: -5 } },
                            xaxis: { categories: months.slice(0, data.length), labels: { style: { fontSize: '10px', colors: '#9ca3af' } },
                                axisBorder: { show: false }, axisTicks: { show: false } },
                            yaxis: { labels: { style: { fontSize: '10px', colors: '#9ca3af' },
                                formatter: function(val) { return val >= 1000 ? (val/1000).toFixed(0) + 'K' : val; } } },
                            tooltip: { y: { formatter: function(val) { return val.toLocaleString(); } } },
                            dataLabels: { enabled: false }
                        }).render();
                    }
                })"
                 style="height: 160px;">
            </div>
        @else
            <div class="h-20 flex items-center justify-center text-xs text-gray-300">Keine Verlaufsdaten</div>
        @endif
    </div>

    {{-- Google Trends --}}
    @if($keyword->trends_sparkline && count($keyword->trends_sparkline) > 3)
        <div class="p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Google Trends</span>
                <div class="flex gap-3 text-[10px] text-gray-400">
                    @if($keyword->trends_average_interest)
                        <span>Avg: <b class="text-gray-600">{{ $keyword->trends_average_interest }}</b></span>
                    @endif
                    @if($keyword->trends_peak_interest)
                        <span>Peak: <b class="text-gray-600">{{ $keyword->trends_peak_interest }}</b></span>
                    @endif
                </div>
            </div>
            <div wire:key="trends-{{ $keyword->id }}">
                @include('seo::partials.sparkline', [
                    'data' => $keyword->trends_sparkline,
                    'color' => '#667eea',
                    'height' => 60,
                    'type' => 'area',
                ])
            </div>
        </div>
    @endif

    {{-- Position History --}}
    @if($positionHistory->isNotEmpty())
        <div class="p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Position-Verlauf</span>
                <span class="text-[10px] text-gray-400">Letzte {{ $positionHistory->count() }} Einträge</span>
            </div>
            <div wire:key="pos-{{ $keyword->id }}">
                @include('seo::partials.sparkline', [
                    'data' => $positionHistory->map(fn ($h) => 101 - $h->position)->toArray(),
                    'color' => '#10b981',
                    'height' => 50,
                    'type' => 'area',
                ])
            </div>
            <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                <span>{{ $positionHistory->first()->tracked_at?->format('d.m.Y') }}</span>
                <span>{{ $positionHistory->last()->tracked_at?->format('d.m.Y') }}</span>
            </div>
        </div>
    @endif

    {{-- SERP Overview (KWFinder-style competitor table) --}}
    @if($keyword->competitors && $keyword->competitors->isNotEmpty())
        <div class="p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">SERP Overview</span>
                <span class="text-[10px] text-gray-400">Top {{ $keyword->competitors->count() }}</span>
            </div>
            <div class="rounded-lg border border-gray-100 overflow-hidden">
                <table class="w-full text-[11px]">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 font-medium">
                            <th class="px-2 py-2 text-left w-7">#</th>
                            <th class="px-2 py-2 text-left">URL</th>
                            <th class="px-2 py-2 text-right">DA</th>
                            <th class="px-2 py-2 text-right">Links</th>
                            <th class="px-2 py-2 text-right">EV</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($keyword->competitors->sortBy('position')->take(10) as $competitor)
                            @php
                                $posColor = match(true) {
                                    $competitor->position <= 3 => 'text-green-600 font-bold',
                                    $competitor->position <= 10 => 'text-gray-700 font-medium',
                                    default => 'text-gray-400',
                                };
                            @endphp
                            <tr class="hover:bg-indigo-50/30 transition-colors">
                                <td class="px-2 py-1.5 {{ $posColor }}">{{ $competitor->position }}</td>
                                <td class="px-2 py-1.5 text-gray-700 truncate max-w-[200px]" title="{{ $competitor->url }}">
                                    <div class="truncate">
                                        <span class="text-gray-900 font-medium">{{ $competitor->domain }}</span>
                                        @if($competitor->url && $competitor->url !== $competitor->domain)
                                            <span class="text-gray-400">{{ Str::limit(str_replace($competitor->domain, '', parse_url($competitor->url, PHP_URL_PATH) ?? ''), 25) }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-2 py-1.5 text-right">
                                    @if($competitor->domain_authority)
                                        <span class="inline-block w-7 text-center rounded text-[10px] font-medium {{ $competitor->domain_authority >= 50 ? 'text-orange-600' : ($competitor->domain_authority >= 30 ? 'text-yellow-600' : 'text-green-600') }}">
                                            {{ $competitor->domain_authority }}
                                        </span>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-right text-gray-500">{{ $competitor->backlinks ? number_format($competitor->backlinks) : '—' }}</td>
                                <td class="px-2 py-1.5 text-right text-gray-500">{{ $competitor->estimated_visits ? number_format($competitor->estimated_visits) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Ranking URLs (own) --}}
    @if($urls->isNotEmpty())
        <div class="p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-medium text-gray-500 uppercase tracking-wider">Deine Rankings</span>
                <span class="text-[10px] text-gray-400">{{ $urls->count() }} URLs</span>
            </div>
            <div class="space-y-2">
                @foreach($urls as $url)
                    <div class="flex items-center gap-2 group">
                        @include('seo::partials.position-badge', ['position' => $url->keywords->first()?->pivot->position, 'change' => null])
                        <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-[12px] text-indigo-600 hover:text-indigo-800 truncate flex-1 group-hover:underline">
                            {{ $url->path ?: '/' }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>
