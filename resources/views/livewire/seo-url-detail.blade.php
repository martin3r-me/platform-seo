<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="URL Detail" icon="heroicon-o-globe-alt" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'URLs', 'route' => 'seo.urls'],
            ['label' => Str::limit($seoUrl->path ?: '/', 30)],
        ]" />
    </x-slot>

    <x-ui-page-container>

        @include('seo::partials.seo-colors')

        {{-- Header --}}
        <div class="bg-white rounded-xl border border-gray-100 p-6 mb-6">
            <div class="flex items-start justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3">
                        <h1 class="text-lg font-semibold text-gray-900 truncate">{{ $seoUrl->url }}</h1>
                        @include('seo::partials.url-status-badge', ['status' => $seoUrl->status, 'httpStatus' => $seoUrl->http_status])
                    </div>
                    <div class="flex items-center gap-4 mt-2 text-xs text-gray-400">
                        <span>{{ $seoUrl->is_own ? 'Eigene URL' : 'Wettbewerber' }}</span>
                        <span>Priorität: {{ $seoUrl->priority }}</span>
                        @if($seoUrl->last_crawled_at)
                            <span>Zuletzt gecrawlt: {{ $seoUrl->last_crawled_at->format('d.m.Y H:i') }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Stats Grid --}}
        <x-ui-stats-grid :cols="5">
            <x-ui-dashboard-tile title="Keywords" :count="$seoUrl->keyword_count" icon="key" variant="primary" />
            <x-ui-dashboard-tile title="Suchvolumen" :count="$seoUrl->total_search_volume ?? 0" icon="magnifying-glass" variant="info" />
            <x-ui-dashboard-tile title="Sichtbarkeit" :count="$urlVisibility['percentage']" icon="eye" variant="success" description="%" />
            <x-ui-dashboard-tile title="Backlinks" :count="$seoUrl->backlink_count ?? 0" icon="link" variant="warning" />
            <x-ui-dashboard-tile title="On-Page Score" :count="$onPage?->overall_score ?? 0" icon="document-check" variant="neutral" description="{{ $onPage?->overall_score !== null ? '%' : '—' }}" />
        </x-ui-stats-grid>

        {{-- Sub-Tabs --}}
        <div x-data="{ tab: 'keywords' }" class="mt-6">
            <div class="flex items-center gap-1 border-b border-gray-100 mb-6">
                <button @click="tab = 'keywords'" :class="tab === 'keywords' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'" class="px-4 py-3 text-sm font-medium">Keywords</button>
                <button @click="tab = 'rankings'" :class="tab === 'rankings' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'" class="px-4 py-3 text-sm font-medium">Rankings</button>
                <button @click="tab = 'backlinks'" :class="tab === 'backlinks' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'" class="px-4 py-3 text-sm font-medium">Backlinks</button>
                <button @click="tab = 'onpage'" :class="tab === 'onpage' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'" class="px-4 py-3 text-sm font-medium">On-Page</button>
                <button @click="tab = 'gsc'" :class="tab === 'gsc' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'" class="px-4 py-3 text-sm font-medium">GSC</button>
                <button @click="tab = 'registrations'" :class="tab === 'registrations' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'" class="px-4 py-3 text-sm font-medium">Registrierungen</button>
                <button @click="tab = 'relationships'" :class="tab === 'relationships' ? 'text-indigo-600 border-b-2 border-indigo-600' : 'text-gray-400 hover:text-gray-600'" class="px-4 py-3 text-sm font-medium">Beziehungen</button>
            </div>

            {{-- Keywords Tab --}}
            <div x-show="tab === 'keywords'">
                @if($keywords->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-gray-400">
                                    <th class="px-4 py-3">Keyword</th>
                                    <th class="px-4 py-3 text-right">Position</th>
                                    <th class="px-4 py-3 text-right">SV</th>
                                    <th class="px-4 py-3 text-right">KD</th>
                                    <th class="px-4 py-3">Intent</th>
                                    <th class="px-4 py-3">Topic</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($keywords as $keyword)
                                    <tr class="border-b border-gray-50">
                                        <td class="px-4 py-2.5 font-medium text-gray-900">{{ $keyword->keyword }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            @include('seo::partials.position-badge', ['position' => $keyword->pivot->position, 'change' => null])
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            @include('seo::partials.sv-badge', ['volume' => $keyword->search_volume])
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            @include('seo::partials.kd-badge', ['value' => $keyword->keyword_difficulty])
                                        </td>
                                        <td class="px-4 py-2.5 text-xs text-gray-500">{{ $keyword->search_intent ? ucfirst($keyword->search_intent) : '' }}</td>
                                        <td class="px-4 py-2.5 text-xs text-gray-500">{{ $keyword->topic ?? '' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-400">Keine Keywords für diese URL.</div>
                @endif
            </div>

            {{-- Rankings Tab --}}
            <div x-show="tab === 'rankings'" x-cloak>
                @if($rankingHistory->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-gray-400">
                                    <th class="px-4 py-3">Keyword</th>
                                    <th class="px-4 py-3 text-right">Position</th>
                                    <th class="px-4 py-3 text-right">Veränderung</th>
                                    <th class="px-4 py-3">SERP Features</th>
                                    <th class="px-4 py-3 text-right">Datum</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rankingHistory as $entry)
                                    <tr class="border-b border-gray-50">
                                        <td class="px-4 py-2.5 font-medium text-gray-900">{{ $entry->keyword?->keyword ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right">
                                            @include('seo::partials.position-badge', ['position' => $entry->position, 'change' => $entry->position_delta])
                                        </td>
                                        <td class="px-4 py-2.5 text-right">
                                            @if($entry->position_delta !== null)
                                                <span class="{{ $entry->position_delta > 0 ? 'text-green-600' : ($entry->position_delta < 0 ? 'text-red-600' : 'text-gray-400') }}">
                                                    {{ $entry->position_delta > 0 ? '+' : '' }}{{ $entry->position_delta }}
                                                </span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-xs text-gray-500">
                                            @if($entry->serp_features)
                                                @foreach((array)$entry->serp_features as $feature)
                                                    <span class="inline-block px-1.5 py-0.5 bg-gray-100 rounded text-[10px] mr-1">{{ $feature }}</span>
                                                @endforeach
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-xs text-gray-400">{{ $entry->tracked_at?->format('d.m.Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-400">Noch keine Ranking-Historie.</div>
                @endif
            </div>

            {{-- Backlinks Tab --}}
            <div x-show="tab === 'backlinks'" x-cloak>
                @if($backlinks->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-gray-400">
                                    <th class="px-4 py-3">Quell-URL</th>
                                    <th class="px-4 py-3">Anchor-Text</th>
                                    <th class="px-4 py-3">Typ</th>
                                    <th class="px-4 py-3 text-right">DA</th>
                                    <th class="px-4 py-3 text-right">Zuletzt gesehen</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($backlinks as $bl)
                                    <tr class="border-b border-gray-50">
                                        <td class="px-4 py-2.5">
                                            <span class="text-gray-900 truncate block max-w-xs">{{ $bl->source_url }}</span>
                                            <span class="text-xs text-gray-400">{{ $bl->source_domain }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-600 truncate max-w-[200px]">{{ $bl->anchor_text ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-xs text-gray-500">{{ $bl->link_type ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $bl->source_domain_authority ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right text-xs text-gray-400">{{ $bl->last_seen_at?->format('d.m.Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-400">Keine Backlinks gefunden.</div>
                @endif
            </div>

            {{-- On-Page Tab --}}
            <div x-show="tab === 'onpage'" x-cloak>
                @if($onPage)
                    <div class="bg-white rounded-xl border border-gray-100 p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-gray-400 mb-2">Title</h4>
                                <p class="text-sm text-gray-900">{{ $onPage->title ?? '—' }}</p>
                            </div>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-gray-400 mb-2">H1</h4>
                                <p class="text-sm text-gray-900">{{ $onPage->h1 ?? '—' }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <h4 class="text-xs uppercase tracking-wider text-gray-400 mb-2">Meta Description</h4>
                                <p class="text-sm text-gray-900">{{ $onPage->meta_description ?? '—' }}</p>
                            </div>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-gray-400 mb-2">Wortanzahl</h4>
                                <p class="text-sm text-gray-900">{{ $onPage->word_count !== null ? number_format($onPage->word_count) : '—' }}</p>
                            </div>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-gray-400 mb-2">Page Speed Score</h4>
                                <p class="text-sm text-gray-900">{{ $onPage->page_speed_score ?? '—' }}</p>
                            </div>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-gray-400 mb-2">Mobile Score</h4>
                                <p class="text-sm text-gray-900">{{ $onPage->mobile_score ?? '—' }}</p>
                            </div>
                            <div>
                                <h4 class="text-xs uppercase tracking-wider text-gray-400 mb-2">Gesamt-Score</h4>
                                @if($onPage->overall_score !== null)
                                    @include('seo::partials.score-gauge', ['value' => $onPage->overall_score, 'label' => 'Score', 'size' => 'md'])
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </div>
                        </div>

                        @if(!empty($onPage->issues))
                            <div class="mt-6">
                                <h4 class="text-xs uppercase tracking-wider text-gray-400 mb-2">Probleme</h4>
                                <div class="space-y-1">
                                    @foreach($onPage->issues as $issue)
                                        <div class="flex items-center gap-2 text-sm text-gray-600">
                                            @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-amber-500')
                                            <span>{{ is_array($issue) ? ($issue['message'] ?? json_encode($issue)) : $issue }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="py-12 text-center text-gray-400">Noch keine On-Page-Analyse durchgeführt.</div>
                @endif
            </div>

            {{-- GSC Tab --}}
            <div x-show="tab === 'gsc'" x-cloak>
                @if($gscData->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-gray-400">
                                    <th class="px-4 py-3">Keyword</th>
                                    <th class="px-4 py-3 text-right">Impressionen</th>
                                    <th class="px-4 py-3 text-right">Klicks</th>
                                    <th class="px-4 py-3 text-right">CTR</th>
                                    <th class="px-4 py-3 text-right">Ø Position</th>
                                    <th class="px-4 py-3 text-right">Datum</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($gscData as $gsc)
                                    <tr class="border-b border-gray-50">
                                        <td class="px-4 py-2.5 font-medium text-gray-900">{{ $gsc->keyword?->keyword ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($gsc->impressions) }}</td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($gsc->clicks) }}</td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($gsc->ctr * 100, 1) }}%</td>
                                        <td class="px-4 py-2.5 text-right">
                                            @include('seo::partials.position-badge', ['position' => round($gsc->avg_position), 'change' => null])
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-xs text-gray-400">{{ $gsc->date?->format('d.m.Y') ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-400">Keine GSC-Daten vorhanden.</div>
                @endif
            </div>

            {{-- Registrations Tab --}}
            <div x-show="tab === 'registrations'" x-cloak>
                @if($registrations->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-gray-400">
                                    <th class="px-4 py-3">Modul</th>
                                    <th class="px-4 py-3">Typ</th>
                                    <th class="px-4 py-3">Grund</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($registrations as $reg)
                                    <tr class="border-b border-gray-50">
                                        <td class="px-4 py-2.5 font-medium text-gray-900">{{ $reg->source_module }}</td>
                                        <td class="px-4 py-2.5 text-gray-600">{{ $reg->source_type ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-gray-600">{{ $reg->reason ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-400">Keine Registrierungen.</div>
                @endif
            </div>

            {{-- Relationships Tab --}}
            <div x-show="tab === 'relationships'" x-cloak>
                @if($relationships->isNotEmpty())
                    <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-gray-100 text-left text-gray-400">
                                    <th class="px-4 py-3">Typ</th>
                                    <th class="px-4 py-3">Richtung</th>
                                    <th class="px-4 py-3">Verbundene URL</th>
                                    <th class="px-4 py-3 text-right">Stärke</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($relationships as $rel)
                                    @php
                                        $isSource = $rel->source_url_id === $seoUrl->id;
                                        $relatedUrl = $isSource ? $rel->targetUrl : $rel->sourceUrl;
                                    @endphp
                                    <tr class="border-b border-gray-50">
                                        <td class="px-4 py-2.5">
                                            <span class="px-2 py-0.5 bg-gray-100 rounded text-xs text-gray-600">{{ $rel->type }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-gray-600 text-xs">{{ $isSource ? 'Ausgehend' : 'Eingehend' }}</td>
                                        <td class="px-4 py-2.5">
                                            @if($relatedUrl)
                                                <span class="text-gray-900 truncate block max-w-md">{{ $relatedUrl->url }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2.5 text-right text-gray-600">{{ $rel->strength ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="py-12 text-center text-gray-400">Keine Beziehungen.</div>
                @endif
            </div>
        </div>

    </x-ui-page-container>
</x-ui-page>
