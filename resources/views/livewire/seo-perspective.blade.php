<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Perspektive" icon="heroicon-o-rectangle-group" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => $heading ?: 'Perspektive'],
        ]">
            @if($entityId && \Illuminate\Support\Facades\Route::has('organization.entities.show'))
                <x-ui-button variant="secondary" size="sm" :href="route('organization.entities.show', $entityId)">
                    @svg('heroicon-o-arrow-top-right-on-square', 'w-4 h-4')
                    <span>Im Org-Baum öffnen</span>
                </x-ui-button>
            @endif
        </x-ui-page-actionbar>
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>

        {{-- Kopf --}}
        <div class="mb-5">
            <h1 class="text-lg font-semibold text-gray-900">{{ $heading ?: 'Perspektive' }}</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $subtitle }}{{ $kpis['nodes'] ? ' · '.$kpis['nodes'].' Knoten' : '' }}</p>
        </div>

        {{-- Perspektive-Zusammenfassung: KPIs (immer sichtbar) --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">URLs</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['own']) }}</div>
                <div class="text-[10px] text-gray-400 mt-1">{{ $kpis['competitors'] }} Wettbewerber</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Sichtbarkeit</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['visibility']) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Keywords</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['keywords']) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Suchvolumen</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['search_volume']) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Backlinks</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['backlinks']) }}</div>
            </div>
            <div class="bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Traffic (30T)</div>
                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($kpis['visitors']) }}</div>
            </div>
        </div>

        {{-- Kontext-Tabs: die Linsen dieser Perspektive --}}
        @php $tabs = ['overview' => 'Übersicht', 'urls' => 'URLs', 'competitors' => 'Wettbewerber', 'recommendations' => 'Empfehlungen', 'clusters' => 'Cluster']; @endphp
        <div class="flex items-center gap-1 border-b border-gray-200 mb-6 overflow-x-auto">
            @foreach($tabs as $key => $label)
                <button wire:click="$set('tab', '{{ $key }}')"
                        class="px-4 py-2 text-[13px] font-medium border-b-2 -mb-px whitespace-nowrap transition-colors {{ $tab === $key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- ============ ÜBERSICHT ============ --}}
        @if($tab === 'overview')
            @if($customerCount > 0)
                <a href="{{ route('seo.perspective.customers', $entityId) }}" wire:navigate
                   class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-[13px] bg-indigo-600 text-white hover:bg-indigo-700 transition-colors shadow-sm mb-6">
                    @svg('heroicon-o-user-group', 'w-4 h-4')
                    <span class="font-medium">Alle Kunden</span>
                    <span class="text-[11px] bg-white/20 rounded px-1.5 py-0.5 tabular-nums">{{ $customerCount }}</span>
                </a>
            @endif

            @if(!empty($relations))
                <div class="mb-6">
                    <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Relationen</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($relations as $rel)
                            <a href="{{ route('seo.perspective.relation', ['entity' => $entityId, 'relation' => $rel['code']]) }}" wire:navigate
                               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12px] bg-white border border-gray-200 text-gray-700 hover:border-indigo-300 hover:text-indigo-700 transition-colors">
                                @svg('heroicon-o-arrows-right-left', 'w-3.5 h-3.5 text-gray-400')
                                <span class="font-medium">{{ $rel['name'] }}</span>
                                <span class="text-[10px] text-gray-400 tabular-nums">{{ $rel['count'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if(!empty($subPerspectives))
                <div class="mb-6">
                    <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Unter-Perspektiven</h2>
                    <div class="flex flex-wrap gap-2">
                        @foreach($subPerspectives as $sub)
                            <a href="{{ route('seo.perspective', $sub['id']) }}" wire:navigate
                               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg text-[12px] bg-white border border-gray-200 text-gray-700 hover:border-indigo-300 hover:text-indigo-700 transition-colors">
                                @svg('heroicon-o-rectangle-group', 'w-3.5 h-3.5 text-gray-400')
                                <span class="font-medium">{{ $sub['name'] ?: ('Knoten #'.$sub['id']) }}</span>
                                <span class="text-[10px] text-gray-400 tabular-nums">{{ $sub['url_count'] }} URLs</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($urls->isNotEmpty())
                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <h2 class="text-[13px] font-semibold text-gray-700">Top-URLs</h2>
                        <button wire:click="$set('tab', 'urls')" class="text-[11px] text-indigo-500 hover:underline">Alle URLs →</button>
                    </div>
                    <div class="divide-y divide-gray-50">
                        @foreach($urls->take(6) as $url)
                            <div class="px-4 py-2.5 flex items-center justify-between gap-3 text-[12px]">
                                <a href="{{ route('seo.urls.show', $url->id) }}" wire:navigate class="text-indigo-600 hover:underline truncate">{{ $url->display_label }}</a>
                                <span class="tabular-nums font-medium text-gray-700 flex-shrink-0">Sicht. {{ number_format($url->visibility_score, 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-[13px] text-gray-400">
                    Keine URLs in dieser Perspektive.
                </div>
            @endif
        @endif

        {{-- ============ URLS (Arbeitsplatz) ============ --}}
        @if($tab === 'urls')
            @if($notice)
                <div class="mb-4 text-[12px] text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">{{ $notice }}</div>
            @endif

            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                    <h2 class="text-[13px] font-semibold text-gray-700">URLs in dieser Perspektive</h2>
                    @if(!empty($selected))
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-[11px] text-gray-500 tabular-nums">{{ count($selected) }} ausgewählt</span>
                            <select wire:model="assignNodeId" class="border border-gray-200 rounded-lg px-2 py-1.5 text-[12px] bg-white">
                                <option value="">Kontext wählen…</option>
                                @foreach($availableNodes as $node)
                                    <option value="{{ $node['id'] }}">{{ $node['name'] }}</option>
                                @endforeach
                            </select>
                            <button wire:click="assignSelected(false)" class="text-[11px] font-medium px-2.5 py-1.5 rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-40" @disabled(!$assignNodeId)>Zuweisen</button>
                            <button wire:click="assignSelected(true)" class="text-[11px] font-medium px-2.5 py-1.5 rounded-lg bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100 disabled:opacity-40" @disabled(!$assignNodeId)>Als Wettbewerber zuweisen</button>
                            <button wire:click="markCompetitor" class="text-[11px] px-2.5 py-1.5 rounded-lg text-gray-500 hover:text-amber-700">Nur als Wettbewerber</button>
                            <button wire:click="clearSelection" class="text-[11px] text-gray-400 hover:text-gray-600">×</button>
                        </div>
                    @endif
                </div>
                @if($urls->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px]">
                            <thead>
                                <tr class="text-left text-[10px] text-gray-400 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                                    <th class="px-4 py-2 w-8">
                                        <button wire:click="selectAll({{ $urls->pluck('id')->toJson() }})" title="Alle auswählen" class="hover:text-gray-600">☐</button>
                                    </th>
                                    <th class="px-4 py-2">URL</th>
                                    <th class="px-4 py-2 text-right">Keywords</th>
                                    <th class="px-4 py-2 text-right">Suchvolumen</th>
                                    <th class="px-4 py-2 text-right">Sichtbarkeit</th>
                                    <th class="px-4 py-2 text-right">Backlinks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                @foreach($urls as $url)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-2">
                                            <input type="checkbox" wire:model.live="selected" value="{{ $url->id }}" class="rounded border-gray-300">
                                        </td>
                                        <td class="px-4 py-2">
                                            <div class="flex items-center gap-2">
                                                @if(!$url->is_own)
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400 flex-shrink-0" title="Wettbewerber"></span>
                                                @endif
                                                <a href="{{ route('seo.urls.show', $url->id) }}" wire:navigate class="text-indigo-600 hover:underline truncate max-w-[320px] block font-medium">{{ $url->display_label }}</a>
                                                @if(!empty($url->provenance_key) && !in_array($url->provenance_key, ['seo', 'competitor']))
                                                    @include('seo::partials.url-provenance-badge', ['key' => $url->provenance_key])
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format($url->keyword_count) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format($url->total_search_volume) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums font-medium text-gray-700">{{ number_format($url->visibility_score, 0) }}</td>
                                        <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format($url->backlink_count) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-8 text-center text-[13px] text-gray-400">Keine URLs in dieser Perspektive.</div>
                @endif
            </div>
        @endif

        {{-- ============ WETTBEWERBER ============ --}}
        @if($tab === 'competitors')
            @if($competitors->isNotEmpty())
                <p class="text-[12px] text-gray-500 mb-3">Domains, die auf denselben Keywords ranken wie die eigenen URLs dieser Perspektive — die reale Konkurrenz um diese Themen.</p>
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
            @else
                <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-[13px] text-gray-400">
                    Keine Wettbewerber für diese Perspektive — sie entstehen aus dem Keyword-Überlapp mit den eigenen URLs, sobald Rankings gemessen sind.
                </div>
            @endif
        @endif

        {{-- ============ EMPFEHLUNGEN ============ --}}
        @if($tab === 'recommendations')
            @if($recommendations->isNotEmpty())
                <div class="space-y-2">
                    @foreach($recommendations as $rec)
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="flex items-start gap-3">
                                <span class="inline-flex w-2 h-2 rounded-full flex-shrink-0 mt-1.5 {{ in_array(strtolower($rec->severity ?? ''), ['critical','high','error','action','danger']) ? 'bg-red-400' : (in_array(strtolower($rec->severity ?? ''), ['medium','warning','warn','watch']) ? 'bg-amber-400' : 'bg-blue-300') }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-[13px] font-medium text-gray-900">{{ $rec->title }}</div>
                                    @if($rec->description)
                                        <div class="text-[11px] text-gray-400 mt-0.5">{{ $rec->description }}</div>
                                    @endif
                                    @if($rec->url)
                                        <a href="{{ route('seo.urls.show', $rec->url->id) }}" wire:navigate class="text-[11px] text-indigo-500 hover:underline mt-1 inline-block">{{ $rec->url->domain }}{{ $rec->url->path && $rec->url->path !== '/' ? $rec->url->path : '' }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-[13px] text-gray-400">
                    Keine offenen Empfehlungen für diese Perspektive.
                </div>
            @endif
        @endif

        {{-- ============ CLUSTER ============ --}}
        @if($tab === 'clusters')
            @if($clusters->isNotEmpty())
                <div class="space-y-2">
                    @foreach($clusters as $cluster)
                        <a href="{{ route('seo.clusters.show', $cluster) }}" wire:navigate class="flex items-center justify-between gap-4 bg-white rounded-lg border border-gray-200 p-4 hover:border-indigo-300 transition-colors">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background: {{ $cluster->color ?: '#94a3b8' }}"></span>
                                <span class="font-medium text-[13px] text-gray-900 truncate">{{ $cluster->name }}</span>
                            </div>
                            <div class="flex items-center gap-6 text-[12px] text-gray-500 flex-shrink-0">
                                <span>{{ $cluster->keyword_count }} KW</span>
                                <span class="tabular-nums">Sicht. {{ number_format($cluster->visibility, 0) }}</span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="bg-white rounded-lg border border-gray-200 p-8 text-center text-[13px] text-gray-400">
                    Noch keine Cluster für diese Perspektive. Cluster entstehen, wenn Keywords zu Themen gebündelt werden.
                </div>
            @endif
        @endif

    </x-ui-page-container>
</x-ui-page>
