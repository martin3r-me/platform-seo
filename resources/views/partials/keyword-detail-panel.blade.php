@props(['keyword', 'urls', 'positionHistory'])

<div class="p-4 space-y-6">

    {{-- KD Gauge + Key Metrics --}}
    <div class="flex items-start gap-4">
        <div wire:key="gauge-{{ $keyword->id }}">
            @include('seo::partials.score-gauge', ['value' => $keyword->keyword_difficulty ?? 0, 'label' => 'KD', 'size' => 'lg'])
        </div>
        <div class="grid grid-cols-2 gap-3 flex-1">
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-[10px] text-gray-500 uppercase tracking-wide">Suchvolumen</div>
                <div class="text-lg font-semibold text-gray-900">{{ number_format($keyword->search_volume ?? 0) }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-[10px] text-gray-500 uppercase tracking-wide">CPC</div>
                <div class="text-lg font-semibold text-gray-900">{{ $keyword->cpc_euro !== null ? number_format($keyword->cpc_euro, 2) . ' €' : '—' }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-[10px] text-gray-500 uppercase tracking-wide">Competition</div>
                <div class="text-lg font-semibold text-gray-900">{{ $keyword->competition !== null ? number_format($keyword->competition, 2) : '—' }}</div>
            </div>
            <div class="bg-gray-50 rounded-lg p-3">
                <div class="text-[10px] text-gray-500 uppercase tracking-wide">Intent</div>
                <div class="text-lg font-semibold text-gray-900">
                    @if($keyword->search_intent)
                        {{ strtoupper(substr($keyword->search_intent, 0, 1)) }}
                        <span class="text-xs font-normal text-gray-500">{{ ucfirst($keyword->search_intent) }}</span>
                    @else
                        —
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Suchvolumen pro Monat --}}
    @if($keyword->monthly_volumes && count($keyword->monthly_volumes) >= 6)
        <div>
            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Suchvolumen / Monat</h4>
            <div wire:key="sv-chart-{{ $keyword->id }}" wire:ignore
                 x-data x-init="$nextTick(() => {
                    if (typeof ApexCharts !== 'undefined') {
                        new ApexCharts($el, {
                            chart: { type: 'bar', height: 180, sparkline: { enabled: true } },
                            series: [{ data: {{ json_encode(array_values($keyword->monthly_volumes)) }} }],
                            colors: ['#6366f1'],
                            plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
                            tooltip: { enabled: true, y: { formatter: (val) => val.toLocaleString() } }
                        }).render();
                    }
                })" style="height: 180px;">
            </div>
        </div>
    @endif

    {{-- Position-Verlauf --}}
    @if($positionHistory->isNotEmpty())
        <div>
            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Position-Verlauf</h4>
            <div wire:key="pos-history-{{ $keyword->id }}">
                @include('seo::partials.sparkline', [
                    'data' => $positionHistory->map(fn ($h) => 101 - $h->position)->toArray(),
                    'color' => '#10b981',
                    'height' => 60,
                ])
            </div>
            <div class="flex justify-between text-[10px] text-gray-400 mt-1">
                <span>{{ $positionHistory->first()->tracked_at?->format('d.m') }}</span>
                <span>{{ $positionHistory->last()->tracked_at?->format('d.m') }}</span>
            </div>
        </div>
    @endif

    {{-- Rankende URLs --}}
    <div>
        <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Rankende URLs</h4>
        @if($urls->isNotEmpty())
            <div class="space-y-2">
                @foreach($urls as $url)
                    <div class="flex items-center gap-2">
                        @include('seo::partials.position-badge', ['position' => $url->keywords->first()?->pivot->position, 'change' => null])
                        <a href="{{ route('seo.urls.show', $url) }}" wire:navigate class="text-sm text-indigo-600 hover:underline truncate">{{ $url->path ?: '/' }}</a>
                        <span class="text-[10px] text-gray-400 shrink-0">{{ $url->domain }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-xs text-gray-400">Keine URLs ranken für dieses Keyword.</div>
        @endif
    </div>

    {{-- SERP-Übersicht --}}
    @if($keyword->competitors && $keyword->competitors->isNotEmpty())
        <div>
            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">SERP-Übersicht</h4>
            <div class="bg-gray-50 rounded-lg overflow-hidden">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500">
                            <th class="px-3 py-1.5 text-left w-8">#</th>
                            <th class="px-3 py-1.5 text-left">Domain</th>
                            <th class="px-3 py-1.5 text-left">URL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($keyword->competitors->sortBy('position')->take(10) as $competitor)
                            <tr class="border-b border-gray-100 {{ $competitor->position <= 3 ? 'bg-green-50/50' : '' }}">
                                <td class="px-3 py-1.5 font-medium {{ $competitor->position <= 3 ? 'text-green-700' : 'text-gray-600' }}">{{ $competitor->position }}</td>
                                <td class="px-3 py-1.5 text-gray-700">{{ Str::limit($competitor->domain, 25) }}</td>
                                <td class="px-3 py-1.5 text-gray-400 truncate max-w-[180px]" title="{{ $competitor->url }}">{{ Str::limit($competitor->url, 30) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Google Trends --}}
    @if($keyword->trends_sparkline && count($keyword->trends_sparkline) > 0)
        <div>
            <h4 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-2">Google Trends</h4>
            <div wire:key="trends-{{ $keyword->id }}">
                @include('seo::partials.sparkline', [
                    'data' => $keyword->trends_sparkline,
                    'color' => '#f59e0b',
                    'height' => 50,
                ])
            </div>
            <div class="flex gap-4 mt-2 text-[10px] text-gray-500">
                @if($keyword->trends_average_interest)
                    <span>Avg: {{ $keyword->trends_average_interest }}</span>
                @endif
                @if($keyword->trends_peak_interest)
                    <span>Peak: {{ $keyword->trends_peak_interest }}</span>
                @endif
            </div>
        </div>
    @endif

</div>
