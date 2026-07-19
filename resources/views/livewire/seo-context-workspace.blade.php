<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Kontext" icon="heroicon-o-rectangle-stack" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_filter([
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => $nodeName ?: ('Knoten #'.$entityId)],
        ])">
            @if(\Illuminate\Support\Facades\Route::has('organization.entities.show'))
                <x-ui-button variant="secondary" size="sm" :href="route('organization.entities.show', $entityId)">
                    @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                    <span>Im Org-Baum öffnen</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => ''])
    </x-slot>

    <x-ui-page-container>

        <p class="text-[13px] text-gray-500 mb-6">Die SEO-Sicht dieses Kontexts: alle hier verankerten URLs samt zentral gemessener Signale. Daten laufen über die Knoten-Verlinkung in den Baum.</p>

        {{-- KPI-Kacheln --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">URLs</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $kpis['urls'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">{{ $kpis['own'] }} eigene</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Sichtbarkeit</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['visibility']) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Traffic (30T)</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['visitors']) }}</div>
                <div class="text-[10px] text-gray-400 mt-1">{{ number_format($kpis['clicks']) }} GSC-Clicks</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Backlinks</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['backlinks']) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Empfehlungen</div>
                <div class="text-2xl font-bold {{ $kpis['open_recommendations'] > 0 ? 'text-amber-600' : 'text-gray-300' }} tabular-nums">{{ $kpis['open_recommendations'] }}</div>
                <div class="text-[10px] text-gray-400 mt-1">offen</div>
            </div>
        </div>

        {{-- URLs im Kontext --}}
        <div class="space-y-2">
            @forelse($signals as $s)
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center gap-4 flex-wrap">
                        <div class="min-w-[200px] flex-1">
                            <a href="{{ route('seo.urls.show', $s['url_id']) }}" wire:navigate class="text-[13px] font-medium text-indigo-600 hover:underline truncate block">
                                {{ $s['path'] && $s['path'] !== '/' ? $s['path'] : $s['domain'] }}
                            </a>
                            <div class="text-[11px] text-gray-400">
                                {{ $s['is_own'] ? 'Eigene URL' : 'Wettbewerber' }}
                                @if(!empty($s['recommendations']))
                                    · <span class="text-amber-600">{{ count($s['recommendations']) }} Empfehlung(en)</span>
                                @endif
                            </div>
                        </div>
                        <div class="text-center w-[80px]">
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">Sichtbar.</div>
                            <div class="text-[13px] font-medium text-gray-700 tabular-nums">{{ number_format($s['visibility'], 0) }}</div>
                        </div>
                        <div class="text-center w-[80px]">
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">Traffic</div>
                            <div class="text-[13px] font-medium text-gray-700 tabular-nums">{{ number_format($s['traffic']['visitors_30d']) }}</div>
                        </div>
                        <div class="text-center w-[80px]">
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">GSC</div>
                            <div class="text-[13px] font-medium text-gray-700 tabular-nums">{{ $s['gsc'] ? number_format($s['gsc']['clicks']) : '—' }}</div>
                        </div>
                        <div class="text-center w-[80px]">
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">Backlinks</div>
                            <div class="text-[13px] font-medium text-gray-700 tabular-nums">{{ number_format($s['backlinks']['count']) }}</div>
                        </div>
                    </div>
                    @if(!empty($s['rankings']))
                    <div class="mt-2 flex flex-wrap gap-1">
                        @foreach(array_slice($s['rankings'], 0, 8) as $r)
                            <span class="text-[10px] px-1.5 py-0.5 bg-gray-50 border border-gray-200 rounded text-gray-600">{{ $r['keyword'] }} · #{{ $r['position'] }}</span>
                        @endforeach
                    </div>
                    @endif
                </div>
            @empty
                <div class="py-16 text-center">
                    <div class="w-12 h-12 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        @svg('heroicon-o-rectangle-stack', 'w-5 h-5 text-gray-400')
                    </div>
                    <p class="text-sm text-gray-500 font-medium mb-1">Noch keine URLs in diesem Kontext</p>
                    <p class="text-xs text-gray-400">Hänge im URL-Detail über „+ Kontext zuweisen" die Kunden-URLs an diesen Knoten — danach messen die Collectors und die Signale erscheinen hier.</p>
                </div>
            @endforelse
        </div>

        {{-- Wettbewerber im Kontext --}}
        @if($competitors->isNotEmpty())
        <div class="mt-8">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-[13px] font-semibold text-gray-700">Wettbewerber im Kontext</h2>
                <a href="{{ route('seo.competitors') }}" wire:navigate class="text-[11px] text-indigo-500 hover:underline">Alle Wettbewerber →</a>
            </div>
            <p class="text-[12px] text-gray-400 mb-3">Domains, die auf denselben Keywords ranken wie die eigenen URLs dieses Kontexts — die reale Konkurrenz um diese Themen.</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                @foreach($competitors as $c)
                    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-[12px] font-medium text-gray-800 truncate">{{ $c->domain }}</div>
                            <div class="text-[10px] text-gray-400 tabular-nums">{{ $c->url_count }} URLs · {{ number_format($c->total_keywords) }} KW</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">Ø Sichtb.</div>
                            <div class="text-[13px] font-medium text-gray-700 tabular-nums">{{ number_format((float) $c->avg_visibility, 0) }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

    </x-ui-page-container>
</x-ui-page>
