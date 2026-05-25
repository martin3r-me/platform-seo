<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar title="" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="array_filter([
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'URLs', 'route' => 'seo.urls'],
            $parentUrl ? ['label' => ($parentUrl->path && $parentUrl->path !== '/') ? Str::limit($parentUrl->path, 20) : $parentUrl->domain, 'href' => route('seo.urls.show', $parentUrl)] : null,
            ['label' => ($seoUrl->path && $seoUrl->path !== '/') ? Str::limit($seoUrl->path, 30) : $seoUrl->domain],
        ])" />
    </x-slot>

    <x-ui-page-container>
        <div class="space-y-6">
            {{-- Header --}}
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-gray-900">{{ $seoUrl->url }}</h1>
                    <div class="flex items-center gap-3 mt-1 text-[11px] text-gray-400">
                        <span>{{ $seoUrl->is_own ? 'Eigene URL' : 'Wettbewerber' }}</span>
                        <span>&middot;</span>
                        <span>Priorität: {{ $seoUrl->priority }}</span>
                        @if($seoUrl->last_crawled_at)
                            <span>&middot;</span>
                            <span>Gecrawlt: {{ $seoUrl->last_crawled_at->format('d.m.Y') }}</span>
                        @endif
                        @if($childUrls->isNotEmpty())
                            <span>&middot;</span>
                            <span>{{ $childUrls->count() }} Unterseiten</span>
                        @endif
                    </div>
                </div>
                @include('seo::partials.url-status-badge', ['status' => $seoUrl->status, 'httpStatus' => $seoUrl->http_status])
            </div>

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Keywords</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $aggKeywordCount }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Suchvolumen</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($aggSearchVolume) }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Sichtbarkeit</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($aggVisibility, 1) }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Backlinks</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $aggBacklinks }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">On-Page</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $onPage?->overall_score ?? '—' }}</div>
                </div>
            </div>

            {{-- Tabs --}}
            <div x-data="{ tab: 'keywords' }">
                <div class="flex items-center gap-1 border-b border-gray-200 mb-6">
                    <button @click="tab = 'keywords'" :class="tab === 'keywords' ? 'text-[#166EE1] border-b-2 border-[#166EE1]' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-3 text-[13px] font-medium transition-colors">Keywords</button>
                    <button @click="tab = 'rankings'" :class="tab === 'rankings' ? 'text-[#166EE1] border-b-2 border-[#166EE1]' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-3 text-[13px] font-medium transition-colors">Rankings</button>
                    <button @click="tab = 'backlinks'" :class="tab === 'backlinks' ? 'text-[#166EE1] border-b-2 border-[#166EE1]' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-3 text-[13px] font-medium transition-colors">Backlinks</button>
                    <button @click="tab = 'onpage'" :class="tab === 'onpage' ? 'text-[#166EE1] border-b-2 border-[#166EE1]' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-3 text-[13px] font-medium transition-colors">On-Page</button>
                    <button @click="tab = 'gsc'" :class="tab === 'gsc' ? 'text-[#166EE1] border-b-2 border-[#166EE1]' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-3 text-[13px] font-medium transition-colors">GSC</button>
                    <button @click="tab = 'relationships'" :class="tab === 'relationships' ? 'text-[#166EE1] border-b-2 border-[#166EE1]' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-3 text-[13px] font-medium transition-colors">Beziehungen</button>
                </div>

                {{-- Keywords Tab --}}
                <div x-show="tab === 'keywords'">
                    @if($keywords->isNotEmpty())
                        <section class="bg-white rounded-lg border border-gray-200">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left">
                                        <th class="px-4 py-3">Keyword</th>
                                        <th class="px-4 py-3">URL</th>
                                        <th class="px-4 py-3 text-right">Position</th>
                                        <th class="px-4 py-3 text-right">SV</th>
                                        <th class="px-4 py-3 text-right">KD</th>
                                        <th class="px-4 py-3">Intent</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($keywords as $keyword)
                                        @php $bestUrl = $keyword->urls->sortBy('pivot.position')->first(); @endphp
                                        <tr class="hover:bg-blue-50/50 transition-colors">
                                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ $keyword->keyword }}</td>
                                            <td class="px-4 py-2.5 text-[11px] text-gray-400">
                                                @if($bestUrl && $bestUrl->id !== $seoUrl->id)
                                                    <a href="{{ route('seo.urls.show', $bestUrl) }}" wire:navigate class="text-[#166EE1] hover:underline">{{ $bestUrl->path ?: '/' }}</a>
                                                @else
                                                    {{ $seoUrl->path ?: '/' }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right">@include('seo::partials.position-badge', ['position' => $bestUrl?->pivot->position, 'change' => null])</td>
                                            <td class="px-4 py-2.5 text-right">@include('seo::partials.sv-badge', ['volume' => $keyword->search_volume])</td>
                                            <td class="px-4 py-2.5 text-right">@include('seo::partials.kd-badge', ['value' => $keyword->keyword_difficulty])</td>
                                            <td class="px-4 py-2.5 text-[11px] text-gray-400">{{ $keyword->search_intent ? ucfirst($keyword->search_intent) : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">Keine Keywords für diese URL.</div>
                    @endif
                </div>

                {{-- Rankings Tab --}}
                <div x-show="tab === 'rankings'" x-cloak>
                    @if($rankingHistory->isNotEmpty())
                        <section class="bg-white rounded-lg border border-gray-200">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left">
                                        <th class="px-4 py-3">Keyword</th>
                                        <th class="px-4 py-3">URL</th>
                                        <th class="px-4 py-3 text-right">Position</th>
                                        <th class="px-4 py-3 text-right">Veränderung</th>
                                        <th class="px-4 py-3">SERP Features</th>
                                        <th class="px-4 py-3 text-right">Datum</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($rankingHistory as $entry)
                                        <tr class="hover:bg-blue-50/50 transition-colors">
                                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ $entry->keyword?->keyword ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-[11px] text-gray-400">
                                                @if($entry->url && $entry->url->id !== $seoUrl->id)
                                                    <a href="{{ route('seo.urls.show', $entry->url) }}" wire:navigate class="text-[#166EE1] hover:underline">{{ $entry->url->path ?: '/' }}</a>
                                                @else
                                                    {{ $seoUrl->path ?: '/' }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right">@include('seo::partials.position-badge', ['position' => $entry->position, 'change' => $entry->position_delta])</td>
                                            <td class="px-4 py-2.5 text-right">
                                                @if($entry->position_delta !== null)
                                                    <span class="{{ $entry->position_delta > 0 ? 'text-green-600' : ($entry->position_delta < 0 ? 'text-red-600' : 'text-gray-400') }}">
                                                        {{ $entry->position_delta > 0 ? '+' : '' }}{{ $entry->position_delta }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-[11px] text-gray-400">
                                                @if($entry->serp_features)
                                                    @foreach((array)$entry->serp_features as $feature)
                                                        <span class="inline-block px-1.5 py-0.5 bg-gray-100 rounded text-[10px] mr-1">{{ $feature }}</span>
                                                    @endforeach
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right text-[11px] text-gray-400">{{ $entry->tracked_at?->format('d.m.Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">Noch keine Ranking-Historie.</div>
                    @endif
                </div>

                {{-- Backlinks Tab --}}
                <div x-show="tab === 'backlinks'" x-cloak>
                    @if($backlinks->isNotEmpty())
                        <section class="bg-white rounded-lg border border-gray-200">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left">
                                        <th class="px-4 py-3">Quell-URL</th>
                                        <th class="px-4 py-3">Anchor-Text</th>
                                        <th class="px-4 py-3">Typ</th>
                                        <th class="px-4 py-3 text-right">DA</th>
                                        <th class="px-4 py-3 text-right">Zuletzt gesehen</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($backlinks as $bl)
                                        <tr class="hover:bg-blue-50/50 transition-colors">
                                            <td class="px-4 py-2.5">
                                                <div class="text-gray-900 truncate max-w-xs">{{ $bl->source_url }}</div>
                                                <div class="text-[11px] text-gray-400">{{ $bl->source_domain }}</div>
                                            </td>
                                            <td class="px-4 py-2.5 text-gray-600 truncate max-w-[200px]">{{ $bl->anchor_text ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-[11px] text-gray-400">{{ $bl->link_type ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-right font-medium text-gray-900">{{ $bl->source_domain_authority ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-right text-[11px] text-gray-400">{{ $bl->last_seen_at?->format('d.m.Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">Keine Backlinks gefunden.</div>
                    @endif
                </div>

                {{-- On-Page Tab --}}
                <div x-show="tab === 'onpage'" x-cloak>
                    @if($onPage)
                        <section class="bg-white rounded-lg border border-gray-200 p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Title</div>
                                    <p class="text-[13px] text-gray-900">{{ $onPage->title ?? '—' }}</p>
                                </div>
                                <div>
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">H1</div>
                                    <p class="text-[13px] text-gray-900">{{ $onPage->h1 ?? '—' }}</p>
                                </div>
                                <div class="md:col-span-2">
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Meta Description</div>
                                    <p class="text-[13px] text-gray-900">{{ $onPage->meta_description ?? '—' }}</p>
                                </div>
                                <div>
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Wortanzahl</div>
                                    <p class="text-[13px] text-gray-900">{{ $onPage->word_count !== null ? number_format($onPage->word_count) : '—' }}</p>
                                </div>
                                <div>
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Page Speed</div>
                                    <p class="text-[13px] text-gray-900">{{ $onPage->page_speed_score ?? '—' }}</p>
                                </div>
                                <div>
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Mobile Score</div>
                                    <p class="text-[13px] text-gray-900">{{ $onPage->mobile_score ?? '—' }}</p>
                                </div>
                                <div>
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Gesamt-Score</div>
                                    <p class="text-[13px] text-gray-900">{{ $onPage->overall_score ?? '—' }}</p>
                                </div>
                            </div>
                            @if(!empty($onPage->issues))
                                <div class="mt-6 pt-4 border-t border-gray-200">
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-2">Probleme</div>
                                    <div class="space-y-1">
                                        @foreach($onPage->issues as $issue)
                                            <div class="flex items-center gap-2 text-[13px] text-gray-700">
                                                @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-amber-500 shrink-0')
                                                <span>{{ is_array($issue) ? ($issue['message'] ?? json_encode($issue)) : $issue }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </section>
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">Noch keine On-Page-Analyse.</div>
                    @endif
                </div>

                {{-- GSC Tab --}}
                <div x-show="tab === 'gsc'" x-cloak>
                    @if($gscData->isNotEmpty())
                        <section class="bg-white rounded-lg border border-gray-200">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left">
                                        <th class="px-4 py-3">Keyword</th>
                                        <th class="px-4 py-3 text-right">Impressionen</th>
                                        <th class="px-4 py-3 text-right">Klicks</th>
                                        <th class="px-4 py-3 text-right">CTR</th>
                                        <th class="px-4 py-3 text-right">Ø Position</th>
                                        <th class="px-4 py-3 text-right">Datum</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($gscData as $gsc)
                                        <tr class="hover:bg-blue-50/50 transition-colors">
                                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ $gsc->keyword?->keyword ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($gsc->impressions) }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($gsc->clicks) }}</td>
                                            <td class="px-4 py-2.5 text-right text-gray-600">{{ number_format($gsc->ctr * 100, 1) }}%</td>
                                            <td class="px-4 py-2.5 text-right">@include('seo::partials.position-badge', ['position' => round($gsc->avg_position), 'change' => null])</td>
                                            <td class="px-4 py-2.5 text-right text-[11px] text-gray-400">{{ $gsc->date?->format('d.m.Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">Keine GSC-Daten vorhanden.</div>
                    @endif
                </div>

                {{-- Relationships Tab --}}
                <div x-show="tab === 'relationships'" x-cloak>
                    @if($relationships->isNotEmpty())
                        <section class="bg-white rounded-lg border border-gray-200">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="border-b border-gray-200 text-left">
                                        <th class="px-4 py-3">Typ</th>
                                        <th class="px-4 py-3">Richtung</th>
                                        <th class="px-4 py-3">Verbundene URL</th>
                                        <th class="px-4 py-3 text-right">Stärke</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    @foreach($relationships as $rel)
                                        @php
                                            $isSource = $rel->source_url_id === $seoUrl->id;
                                            $relatedUrl = $isSource ? $rel->targetUrl : $rel->sourceUrl;
                                        @endphp
                                        <tr class="hover:bg-blue-50/50 transition-colors">
                                            <td class="px-4 py-2.5">
                                                <span class="px-1.5 py-0.5 bg-gray-100 rounded text-[11px] text-gray-600">{{ $rel->type }}</span>
                                            </td>
                                            <td class="px-4 py-2.5 text-[11px] text-gray-400">{{ $isSource ? 'Ausgehend' : 'Eingehend' }}</td>
                                            <td class="px-4 py-2.5">
                                                @if($relatedUrl)
                                                    <a href="{{ route('seo.urls.show', $relatedUrl) }}" wire:navigate class="text-[#166EE1] hover:underline truncate block max-w-md">{{ $relatedUrl->url }}</a>
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right text-gray-600">{{ $rel->strength ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">Keine Beziehungen.</div>
                    @endif
                </div>
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        @include('seo::partials.sidebar', ['active' => 'urls'])
    </x-slot>

    <x-slot name="activity">
        <x-ui-page-sidebar title="URL-Details" width="w-80" :defaultOpen="false" storeKey="activityOpen" side="right">
            <div class="p-6 space-y-6">
                {{-- Properties --}}
                <div>
                    <h3 class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-3">Eigenschaften</h3>
                    <div class="space-y-3">
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="text-[11px] text-gray-400">Domain</div>
                            <div class="text-[13px] font-medium text-gray-900">{{ $seoUrl->domain }}</div>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="text-[11px] text-gray-400">Pfad</div>
                            <div class="text-[13px] font-medium text-gray-900">{{ $seoUrl->path ?: '/' }}</div>
                        </div>
                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                            <div class="text-[11px] text-gray-400">HTTP Status</div>
                            <div class="text-[13px] font-medium text-gray-900">{{ $seoUrl->http_status ?? '—' }}</div>
                        </div>
                    </div>
                </div>

            </div>
        </x-ui-page-sidebar>
    </x-slot>
</x-ui-page>
