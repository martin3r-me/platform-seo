<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="Cluster" icon="heroicon-o-squares-2x2" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Cluster', 'route' => 'seo.clusters'],
            ['label' => $cluster->name],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center gap-2.5">
                <span class="w-3 h-3 rounded-full flex-shrink-0" style="background: {{ $cluster->color ?: '#94a3b8' }}"></span>
                <h1 class="text-xl font-semibold text-gray-900">{{ $cluster->name }}</h1>
            </div>
            @if($cluster->description)
                <p class="text-[13px] text-gray-500 -mt-3">{{ $cluster->description }}</p>
            @endif

            {{-- Kontext-Zuweisung --}}
            @if(!empty($contextNodes) || !empty($availableNodes))
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Kontext</span>
                    @foreach($contextNodes as $node)
                        <span class="inline-flex items-center gap-1.5 pl-2.5 pr-1 py-1 rounded-full text-[11px] font-medium bg-gray-50 text-gray-600 border border-gray-200">
                            @svg('heroicon-o-rectangle-stack', 'w-3 h-3')
                            <a href="{{ route('seo.context', $node['id']) }}" wire:navigate class="hover:text-gray-900">{{ $node['name'] ?? 'Knoten #'.$node['id'] }}</a>
                            <button wire:click="removeFromNode({{ $node['id'] }})" title="Aus Kontext entfernen"
                                    class="ml-0.5 w-4 h-4 flex items-center justify-center rounded-full text-gray-400 hover:text-red-600 hover:bg-red-50">
                                @svg('heroicon-o-x-mark', 'w-3 h-3')
                            </button>
                        </span>
                    @endforeach
                    @if(!empty($availableNodes))
                        <select x-data
                                x-on:change="if($event.target.value){ $wire.assignToNode(parseInt($event.target.value)); $event.target.value=''; }"
                                class="text-[11px] border border-dashed border-gray-300 rounded-full px-2.5 py-1 bg-white text-gray-500 hover:border-gray-400 focus:outline-none">
                            <option value="">+ Kontext zuweisen…</option>
                            @foreach($availableNodes as $n)
                                <option value="{{ $n['id'] }}">{{ $n['name'] }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @endif

            {{-- KPIs + Trajektorie --}}
            @php
                $health = $cluster->health_score;
                $healthColor = match(true) {
                    $health === null => 'text-gray-300',
                    $health >= 70 => 'text-green-600',
                    $health >= 40 => 'text-amber-600',
                    default => 'text-red-500',
                };
            @endphp
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">Abdeckung</div>
                        <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format((float) $cluster->coverage_pct, 0) }}%</div>
                        <div class="text-[10px] text-gray-400 mt-1">{{ $cluster->covered_keywords }}/{{ $cluster->keyword_count }} Keywords</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">Top 3 / 10</div>
                        <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $cluster->top3_count }}/{{ $cluster->top10_count }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">Sichtbarkeit</div>
                        <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($cluster->visibility, 0) }}</div>
                    </div>
                    <div class="bg-white rounded-lg border border-gray-200 p-4">
                        <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-1">Health</div>
                        <div class="text-2xl font-bold {{ $healthColor }} tabular-nums">{{ $health ?? '—' }}</div>
                    </div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] text-gray-400 uppercase tracking-wide mb-2">Trajektorie (Sichtbarkeit, 90 T)</div>
                    @if(count($trajectory) > 1)
                        @include('seo::partials.sparkline', ['data' => $trajectory, 'color' => '#0e6e78', 'height' => 60, 'type' => 'area'])
                    @else
                        <div class="text-[12px] text-gray-300 py-4 text-center">Noch keine Zeitreihe</div>
                    @endif
                </div>
            </div>

            {{-- Content-Briefs --}}
            @if($contentBriefs->isNotEmpty())
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <h2 class="text-[13px] font-semibold text-gray-700 mb-3">Content-Briefs</h2>
                    <div class="space-y-1.5">
                        @foreach($contentBriefs as $brief)
                            <div class="flex items-center gap-2 text-[12px]">
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-indigo-50 text-indigo-600 rounded">{{ $brief->pivot->role ?? 'primary' }}</span>
                                <span class="font-medium text-gray-800">{{ $brief->name }}</span>
                                <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 bg-gray-100 text-gray-500 rounded">{{ $brief->status }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Keywords --}}
            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100">
                    <h2 class="text-[13px] font-semibold text-gray-700">Keywords ({{ $keywords->count() }})</h2>
                </div>
                @if($keywords->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-[12px]">
                        <thead>
                            <tr class="text-left text-[10px] text-gray-400 uppercase tracking-wider border-b border-gray-100 bg-gray-50">
                                <th class="px-4 py-2">Keyword</th>
                                <th class="px-4 py-2 text-right">Beste Pos.</th>
                                <th class="px-4 py-2 text-right">Volumen</th>
                                <th class="px-4 py-2 text-right">KD</th>
                                <th class="px-4 py-2">Intent</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($keywords as $kw)
                                @php $pos = $bestPosition[$kw->id] ?? null; @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-medium text-gray-800">{{ $kw->keyword }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">
                                        @if($pos !== null)
                                            @include('seo::partials.position-badge', ['position' => (int) $pos, 'change' => null])
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ number_format($kw->search_volume) }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums text-gray-600">{{ $kw->keyword_difficulty ?? '—' }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $kw->search_intent ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                    <div class="p-8 text-center text-[13px] text-gray-400">Keine Keywords in diesem Cluster.</div>
                @endif
            </div>
        </div>
    </x-ui-page-container>
</x-ui-page>
