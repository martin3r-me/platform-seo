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

        <div class="mb-6">
            <h1 class="text-lg font-semibold text-gray-900">{{ $heading ?: 'Perspektive' }}</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $subtitle }}{{ $kpis['nodes'] ? ' · '.$kpis['nodes'].' Knoten' : '' }}</p>
        </div>

        {{-- Aggregierte KPIs (eigene URLs) --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-8">
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

        {{-- Relationen als Unter-Perspektiven (datengetrieben, z.B. „alle Kunden") --}}
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

        {{-- Unter-Perspektiven (Kind-Knoten / Relationsziele) zum Weiterdrillen --}}
        @if(!empty($subPerspectives))
            <div class="mb-8">
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

        @if($notice)
            <div class="mb-4 text-[12px] text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">{{ $notice }}</div>
        @endif

        {{-- URLs der Perspektive + Arbeitsplatz (Auswahl → zuweisen/klassifizieren) --}}
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
                <div class="p-8 text-center text-[13px] text-gray-400">
                    Keine URLs in dieser Perspektive.
                </div>
            @endif
        </div>

    </x-ui-page-container>
</x-ui-page>
