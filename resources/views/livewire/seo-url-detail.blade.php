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

    <x-slot name="sidebar">
        <x-ui-page-sidebar title="URL-Baum" width="w-64" :defaultOpen="true" storeKey="seoUrlTreeOpen">
            <div class="p-3 space-y-1">
                {{-- Back to URLs --}}
                <a href="{{ route('seo.urls') }}" wire:navigate
                   class="flex items-center gap-2 px-3 py-2 rounded-md text-xs text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)] transition-colors mb-2">
                    @svg('heroicon-o-arrow-left', 'w-3.5 h-3.5')
                    <span>Alle URLs</span>
                </a>

                {{-- Root URL (current) --}}
                <div class="flex items-center gap-2 px-3 py-2 rounded-md text-sm bg-[var(--ui-primary-10)] text-[var(--ui-primary)] font-medium">
                    @svg('heroicon-o-globe-alt', 'w-4 h-4 flex-shrink-0')
                    <span class="truncate">{{ $seoUrl->path ?: '/' }}</span>
                </div>

                {{-- Child URLs --}}
                @if($childUrls->isNotEmpty())
                    <div class="ml-4 space-y-0.5 border-l border-[var(--ui-border)]/40 pl-2 mt-1">
                        @foreach($childUrls as $child)
                            <a href="{{ route('seo.urls.show', $child) }}" wire:navigate
                               class="flex items-center gap-2 px-2 py-1.5 rounded text-xs text-[var(--ui-muted)] hover:bg-[var(--ui-muted-5)] hover:text-[var(--ui-secondary)] transition-colors">
                                @svg('heroicon-o-document', 'w-3.5 h-3.5 flex-shrink-0')
                                <span class="truncate">{{ $child->path ?: '/' }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </x-ui-page-sidebar>
    </x-slot>

    <x-ui-page-container spacing="space-y-6">

        @include('seo::partials.seo-colors')

        {{-- Header --}}
        <div class="flex items-start gap-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-[var(--ui-secondary)] truncate">{{ $seoUrl->url }}</h1>
                    @include('seo::partials.url-status-badge', ['status' => $seoUrl->status, 'httpStatus' => $seoUrl->http_status])
                </div>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2 text-xs text-[var(--ui-muted)]">
                    <span>{{ $seoUrl->is_own ? 'Eigene URL' : 'Wettbewerber' }}</span>
                    <span>Priorität: {{ $seoUrl->priority }}</span>
                    @if($seoUrl->last_crawled_at)
                        <span>Zuletzt gecrawlt: {{ $seoUrl->last_crawled_at->format('d.m.Y H:i') }}</span>
                    @endif
                    @if($childUrls->isNotEmpty())
                        <span class="inline-flex items-center gap-1">
                            @svg('heroicon-o-document-duplicate', 'w-3.5 h-3.5')
                            {{ $childUrls->count() }} Unterseiten
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Two-column layout: Main + Properties Sidebar --}}
        <div class="flex flex-col lg:flex-row gap-8">
            {{-- Main Area --}}
            <div class="flex-1 min-w-0 space-y-6">

                {{-- Stats Grid (aggregated) --}}
                <x-ui-stats-grid :cols="5">
                    <x-ui-dashboard-tile title="Keywords" :count="$aggKeywordCount" icon="key" variant="primary" />
                    <x-ui-dashboard-tile title="Suchvolumen" :count="$aggSearchVolume" icon="magnifying-glass" variant="info" />
                    <x-ui-dashboard-tile title="Sichtbarkeit" :count="round($aggVisibility, 1)" icon="eye" variant="success" />
                    <x-ui-dashboard-tile title="Backlinks" :count="$aggBacklinks" icon="link" variant="warning" />
                    <x-ui-dashboard-tile title="On-Page Score" :count="$onPage?->overall_score ?? 0" icon="document-check" variant="neutral" description="{{ $onPage?->overall_score !== null ? '%' : '—' }}" />
                </x-ui-stats-grid>

                {{-- Sub-Tabs --}}
                <div x-data="{ tab: 'keywords' }">
                    <div class="flex items-center gap-1 border-b border-[var(--ui-border)]/40 mb-6">
                        <button @click="tab = 'keywords'" :class="tab === 'keywords' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'" class="px-4 py-3 text-sm font-medium">Keywords</button>
                        <button @click="tab = 'rankings'" :class="tab === 'rankings' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'" class="px-4 py-3 text-sm font-medium">Rankings</button>
                        <button @click="tab = 'backlinks'" :class="tab === 'backlinks' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'" class="px-4 py-3 text-sm font-medium">Backlinks</button>
                        <button @click="tab = 'onpage'" :class="tab === 'onpage' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'" class="px-4 py-3 text-sm font-medium">On-Page</button>
                        <button @click="tab = 'gsc'" :class="tab === 'gsc' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'" class="px-4 py-3 text-sm font-medium">GSC</button>
                        <button @click="tab = 'registrations'" :class="tab === 'registrations' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'" class="px-4 py-3 text-sm font-medium">Registrierungen</button>
                        <button @click="tab = 'relationships'" :class="tab === 'relationships' ? 'text-[var(--ui-primary)] border-b-2 border-[var(--ui-primary)]' : 'text-[var(--ui-muted)] hover:text-[var(--ui-secondary)]'" class="px-4 py-3 text-sm font-medium">Beziehungen</button>
                    </div>

                    {{-- Keywords Tab --}}
                    <div x-show="tab === 'keywords'">
                        @if($keywords->isNotEmpty())
                            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-[var(--ui-border)]/40 text-left text-[var(--ui-muted)]">
                                            <th class="px-4 py-3">Keyword</th>
                                            <th class="px-4 py-3">URL</th>
                                            <th class="px-4 py-3 text-right">Position</th>
                                            <th class="px-4 py-3 text-right">SV</th>
                                            <th class="px-4 py-3 text-right">KD</th>
                                            <th class="px-4 py-3">Intent</th>
                                            <th class="px-4 py-3">Topic</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($keywords as $keyword)
                                            @php
                                                $bestUrl = $keyword->urls->sortBy('pivot.position')->first();
                                            @endphp
                                            <tr class="border-b border-[var(--ui-border)]/20">
                                                <td class="px-4 py-2.5 font-medium text-[var(--ui-secondary)]">{{ $keyword->keyword }}</td>
                                                <td class="px-4 py-2.5 text-xs text-[var(--ui-muted)]">
                                                    @if($bestUrl && $bestUrl->id !== $seoUrl->id)
                                                        <a href="{{ route('seo.urls.show', $bestUrl) }}" wire:navigate class="text-[var(--ui-primary)] hover:underline truncate block max-w-[150px]">
                                                            {{ $bestUrl->path ?: '/' }}
                                                        </a>
                                                    @else
                                                        <span>{{ $seoUrl->path ?: '/' }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @include('seo::partials.position-badge', ['position' => $bestUrl?->pivot->position, 'change' => null])
                                                </td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @include('seo::partials.sv-badge', ['volume' => $keyword->search_volume])
                                                </td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @include('seo::partials.kd-badge', ['value' => $keyword->keyword_difficulty])
                                                </td>
                                                <td class="px-4 py-2.5 text-xs text-[var(--ui-muted)]">{{ $keyword->search_intent ? ucfirst($keyword->search_intent) : '' }}</td>
                                                <td class="px-4 py-2.5 text-xs text-[var(--ui-muted)]">{{ $keyword->topic ?? '' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-12 text-center text-[var(--ui-muted)]">Keine Keywords für diese URL.</div>
                        @endif
                    </div>

                    {{-- Rankings Tab --}}
                    <div x-show="tab === 'rankings'" x-cloak>
                        @if($rankingHistory->isNotEmpty())
                            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-[var(--ui-border)]/40 text-left text-[var(--ui-muted)]">
                                            <th class="px-4 py-3">Keyword</th>
                                            <th class="px-4 py-3">URL</th>
                                            <th class="px-4 py-3 text-right">Position</th>
                                            <th class="px-4 py-3 text-right">Veränderung</th>
                                            <th class="px-4 py-3">SERP Features</th>
                                            <th class="px-4 py-3 text-right">Datum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rankingHistory as $entry)
                                            <tr class="border-b border-[var(--ui-border)]/20">
                                                <td class="px-4 py-2.5 font-medium text-[var(--ui-secondary)]">{{ $entry->keyword?->keyword ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-xs text-[var(--ui-muted)]">
                                                    @if($entry->url && $entry->url->id !== $seoUrl->id)
                                                        <a href="{{ route('seo.urls.show', $entry->url) }}" wire:navigate class="text-[var(--ui-primary)] hover:underline truncate block max-w-[150px]">
                                                            {{ $entry->url->path ?: '/' }}
                                                        </a>
                                                    @else
                                                        <span>{{ $seoUrl->path ?: '/' }}</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @include('seo::partials.position-badge', ['position' => $entry->position, 'change' => $entry->position_delta])
                                                </td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @if($entry->position_delta !== null)
                                                        <span class="{{ $entry->position_delta > 0 ? 'text-green-600' : ($entry->position_delta < 0 ? 'text-red-600' : 'text-[var(--ui-muted)]') }}">
                                                            {{ $entry->position_delta > 0 ? '+' : '' }}{{ $entry->position_delta }}
                                                        </span>
                                                    @else
                                                        <span class="text-[var(--ui-muted)]/50">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-xs text-[var(--ui-muted)]">
                                                    @if($entry->serp_features)
                                                        @foreach((array)$entry->serp_features as $feature)
                                                            <span class="inline-block px-1.5 py-0.5 bg-[var(--ui-muted-5)] rounded text-[10px] mr-1">{{ $feature }}</span>
                                                        @endforeach
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right text-xs text-[var(--ui-muted)]">{{ $entry->tracked_at?->format('d.m.Y') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-12 text-center text-[var(--ui-muted)]">Noch keine Ranking-Historie.</div>
                        @endif
                    </div>

                    {{-- Backlinks Tab --}}
                    <div x-show="tab === 'backlinks'" x-cloak>
                        @if($backlinks->isNotEmpty())
                            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-[var(--ui-border)]/40 text-left text-[var(--ui-muted)]">
                                            <th class="px-4 py-3">Quell-URL</th>
                                            <th class="px-4 py-3">Anchor-Text</th>
                                            <th class="px-4 py-3">Typ</th>
                                            <th class="px-4 py-3 text-right">DA</th>
                                            <th class="px-4 py-3 text-right">Zuletzt gesehen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($backlinks as $bl)
                                            <tr class="border-b border-[var(--ui-border)]/20">
                                                <td class="px-4 py-2.5">
                                                    <span class="text-[var(--ui-secondary)] truncate block max-w-xs">{{ $bl->source_url }}</span>
                                                    <span class="text-xs text-[var(--ui-muted)]">{{ $bl->source_domain }}</span>
                                                </td>
                                                <td class="px-4 py-2.5 text-[var(--ui-muted)] truncate max-w-[200px]">{{ $bl->anchor_text ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-xs text-[var(--ui-muted)]">{{ $bl->link_type ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-right text-[var(--ui-secondary)]">{{ $bl->source_domain_authority ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-right text-xs text-[var(--ui-muted)]">{{ $bl->last_seen_at?->format('d.m.Y') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-12 text-center text-[var(--ui-muted)]">Keine Backlinks gefunden.</div>
                        @endif
                    </div>

                    {{-- On-Page Tab --}}
                    <div x-show="tab === 'onpage'" x-cloak>
                        @if($onPage)
                            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 p-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wider text-[var(--ui-muted)] mb-2">Title</h4>
                                        <p class="text-sm text-[var(--ui-secondary)]">{{ $onPage->title ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wider text-[var(--ui-muted)] mb-2">H1</h4>
                                        <p class="text-sm text-[var(--ui-secondary)]">{{ $onPage->h1 ?? '—' }}</p>
                                    </div>
                                    <div class="md:col-span-2">
                                        <h4 class="text-xs uppercase tracking-wider text-[var(--ui-muted)] mb-2">Meta Description</h4>
                                        <p class="text-sm text-[var(--ui-secondary)]">{{ $onPage->meta_description ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wider text-[var(--ui-muted)] mb-2">Wortanzahl</h4>
                                        <p class="text-sm text-[var(--ui-secondary)]">{{ $onPage->word_count !== null ? number_format($onPage->word_count) : '—' }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wider text-[var(--ui-muted)] mb-2">Page Speed Score</h4>
                                        <p class="text-sm text-[var(--ui-secondary)]">{{ $onPage->page_speed_score ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wider text-[var(--ui-muted)] mb-2">Mobile Score</h4>
                                        <p class="text-sm text-[var(--ui-secondary)]">{{ $onPage->mobile_score ?? '—' }}</p>
                                    </div>
                                    <div>
                                        <h4 class="text-xs uppercase tracking-wider text-[var(--ui-muted)] mb-2">Gesamt-Score</h4>
                                        @if($onPage->overall_score !== null)
                                            @include('seo::partials.score-gauge', ['value' => $onPage->overall_score, 'label' => 'Score', 'size' => 'md'])
                                        @else
                                            <span class="text-[var(--ui-muted)]/50">—</span>
                                        @endif
                                    </div>
                                </div>

                                @if(!empty($onPage->issues))
                                    <div class="mt-6">
                                        <h4 class="text-xs uppercase tracking-wider text-[var(--ui-muted)] mb-2">Probleme</h4>
                                        <div class="space-y-1">
                                            @foreach($onPage->issues as $issue)
                                                <div class="flex items-center gap-2 text-sm text-[var(--ui-secondary)]">
                                                    @svg('heroicon-o-exclamation-triangle', 'w-4 h-4 text-amber-500')
                                                    <span>{{ is_array($issue) ? ($issue['message'] ?? json_encode($issue)) : $issue }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="py-12 text-center text-[var(--ui-muted)]">Noch keine On-Page-Analyse durchgeführt.</div>
                        @endif
                    </div>

                    {{-- GSC Tab --}}
                    <div x-show="tab === 'gsc'" x-cloak>
                        @if($gscData->isNotEmpty())
                            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-[var(--ui-border)]/40 text-left text-[var(--ui-muted)]">
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
                                            <tr class="border-b border-[var(--ui-border)]/20">
                                                <td class="px-4 py-2.5 font-medium text-[var(--ui-secondary)]">{{ $gsc->keyword?->keyword ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-right text-[var(--ui-muted)]">{{ number_format($gsc->impressions) }}</td>
                                                <td class="px-4 py-2.5 text-right text-[var(--ui-muted)]">{{ number_format($gsc->clicks) }}</td>
                                                <td class="px-4 py-2.5 text-right text-[var(--ui-muted)]">{{ number_format($gsc->ctr * 100, 1) }}%</td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @include('seo::partials.position-badge', ['position' => round($gsc->avg_position), 'change' => null])
                                                </td>
                                                <td class="px-4 py-2.5 text-right text-xs text-[var(--ui-muted)]">{{ $gsc->date?->format('d.m.Y') ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-12 text-center text-[var(--ui-muted)]">Keine GSC-Daten vorhanden.</div>
                        @endif
                    </div>

                    {{-- Registrations Tab --}}
                    <div x-show="tab === 'registrations'" x-cloak>
                        @if($registrations->isNotEmpty())
                            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-[var(--ui-border)]/40 text-left text-[var(--ui-muted)]">
                                            <th class="px-4 py-3">Modul</th>
                                            <th class="px-4 py-3">Typ</th>
                                            <th class="px-4 py-3">Grund</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($registrations as $reg)
                                            <tr class="border-b border-[var(--ui-border)]/20">
                                                <td class="px-4 py-2.5 font-medium text-[var(--ui-secondary)]">{{ $reg->source_module }}</td>
                                                <td class="px-4 py-2.5 text-[var(--ui-muted)]">{{ $reg->source_type ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-[var(--ui-muted)]">{{ $reg->reason ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-12 text-center text-[var(--ui-muted)]">Keine Registrierungen.</div>
                        @endif
                    </div>

                    {{-- Relationships Tab --}}
                    <div x-show="tab === 'relationships'" x-cloak>
                        @if($relationships->isNotEmpty())
                            <div class="bg-[var(--ui-surface)] rounded-xl border border-[var(--ui-border)]/60 overflow-hidden">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-[var(--ui-border)]/40 text-left text-[var(--ui-muted)]">
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
                                            <tr class="border-b border-[var(--ui-border)]/20">
                                                <td class="px-4 py-2.5">
                                                    <span class="px-2 py-0.5 bg-[var(--ui-muted-5)] rounded text-xs text-[var(--ui-muted)]">{{ $rel->type }}</span>
                                                </td>
                                                <td class="px-4 py-2.5 text-[var(--ui-muted)] text-xs">{{ $isSource ? 'Ausgehend' : 'Eingehend' }}</td>
                                                <td class="px-4 py-2.5">
                                                    @if($relatedUrl)
                                                        <a href="{{ route('seo.urls.show', $relatedUrl) }}" wire:navigate class="text-[var(--ui-primary)] hover:underline truncate block max-w-md">
                                                            {{ $relatedUrl->url }}
                                                        </a>
                                                    @else
                                                        <span class="text-[var(--ui-muted)]/50">—</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right text-[var(--ui-muted)]">{{ $rel->strength ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="py-12 text-center text-[var(--ui-muted)]">Keine Beziehungen.</div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Properties Sidebar (right, like task view) --}}
            <div class="lg:w-72 flex-shrink-0">
                <div class="lg:sticky lg:top-4 space-y-1">
                    <h3 class="text-[10px] font-semibold uppercase tracking-wider text-[var(--ui-muted)] mb-2 px-2">Eigenschaften</h3>

                    <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                        <span class="text-xs text-[var(--ui-muted)]">Status</span>
                        <span class="text-xs font-medium text-[var(--ui-secondary)]">
                            @include('seo::partials.url-status-badge', ['status' => $seoUrl->status, 'httpStatus' => $seoUrl->http_status])
                        </span>
                    </div>

                    <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                        <span class="text-xs text-[var(--ui-muted)]">Domain</span>
                        <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $seoUrl->domain }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                        <span class="text-xs text-[var(--ui-muted)]">Pfad</span>
                        <span class="text-xs font-medium text-[var(--ui-secondary)] truncate max-w-[120px]">{{ $seoUrl->path ?: '/' }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                        <span class="text-xs text-[var(--ui-muted)]">Priorität</span>
                        <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $seoUrl->priority }}</span>
                    </div>

                    <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                        <span class="text-xs text-[var(--ui-muted)]">Typ</span>
                        <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $seoUrl->is_own ? 'Eigene URL' : 'Wettbewerber' }}</span>
                    </div>

                    @if($seoUrl->last_crawled_at)
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                            <span class="text-xs text-[var(--ui-muted)]">Letzter Crawl</span>
                            <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $seoUrl->last_crawled_at->format('d.m.Y') }}</span>
                        </div>
                    @endif

                    @if($seoUrl->http_status)
                        <div class="flex items-center justify-between py-2 px-3 rounded-lg hover:bg-[var(--ui-muted-5)] transition-colors">
                            <span class="text-xs text-[var(--ui-muted)]">HTTP Status</span>
                            <span class="text-xs font-medium text-[var(--ui-secondary)]">{{ $seoUrl->http_status }}</span>
                        </div>
                    @endif

                    {{-- Separator --}}
                    <div class="border-t border-[var(--ui-border)]/40 my-2"></div>

                    <div class="flex items-center justify-between py-1.5 px-3">
                        <span class="text-[10px] text-[var(--ui-muted)]">Unterseiten</span>
                        <span class="text-[10px] text-[var(--ui-secondary)]">{{ $childUrls->count() }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1.5 px-3">
                        <span class="text-[10px] text-[var(--ui-muted)]">Erstellt</span>
                        <span class="text-[10px] text-[var(--ui-secondary)]">{{ $seoUrl->created_at->format('d.m.Y') }}</span>
                    </div>
                </div>
            </div>
        </div>

    </x-ui-page-container>
</x-ui-page>
