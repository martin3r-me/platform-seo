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
        @php
            $visHint = null;
            if ($visibilityDelta !== null && $visibilityDelta !== 0) {
                $visHint = ($visibilityDelta > 0 ? '▲ +' : '▼ ').number_format($visibilityDelta).' · 30 T';
            }
        @endphp
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
            <x-nx-stat label="URLs" :value="number_format($kpis['own'])" :hint="$kpis['competitors'].' Wettbewerber'" />
            <x-nx-stat label="Sichtbarkeit" :value="number_format($kpis['visibility'])" :hint="$visHint" />
            <x-nx-stat label="Keywords" :value="number_format($kpis['keywords'])" />
            <x-nx-stat label="Suchvolumen" :value="number_format($kpis['search_volume'])" />
            <x-nx-stat label="Backlinks" :value="$kpis['backlinks'] === null ? '—' : number_format($kpis['backlinks'])" :hint="$kpis['backlinks'] === null ? 'nicht im Profil' : null" />
            <x-nx-stat label="Traffic (30T)" :value="$kpis['visitors'] === null ? '—' : number_format($kpis['visitors'])" :hint="$kpis['visitors'] === null ? 'nicht im Profil' : null" />
        </div>

        {{-- Daten-Profil dieses Kunden: setzt die Tiefe (und Kosten) für alle eigenen Arbeits-URLs --}}
        @if($entityId)
            <div class="flex items-center justify-between flex-wrap gap-3 mb-6 bg-white rounded-lg border border-gray-200 p-3">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide">Daten-Profil (Kunde)</span>
                    @php($__activeProfile = $this->nodeProfile())
                    <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5">
                        @foreach(array_keys(config('seo.data_profiles.own', [])) as $p)
                            <button wire:click="setNodeProfile('{{ $p }}')"
                                    wire:confirm="Profil „{{ ucfirst($p) }}" auf ALLE eigenen URLs dieses Kunden setzen?"
                                    @class([
                                        'px-3 py-1.5 text-[12px] rounded-md transition-colors',
                                        'bg-white text-gray-900 shadow-sm font-medium' => $p === $__activeProfile,
                                        'text-gray-600 hover:text-gray-900 hover:bg-white' => $p !== $__activeProfile,
                                    ])>
                                {{ ucfirst($p) }}
                            </button>
                        @endforeach
                    </div>
                    @if($__activeProfile === 'gemischt')
                        <span class="text-[11px] text-amber-700" title="Die eigenen URLs dieses Kunden haben unterschiedliche Profile">gemischt</span>
                    @endif
                </div>
                <div class="text-[12px] text-gray-500">
                    <span class="text-gray-400 uppercase tracking-wide text-[10px]">Kosten</span>
                    <span class="font-semibold text-gray-800 tabular-nums ml-1">{{ number_format($this->nodeMonthlyCents() / 100, 2, ',', '.') }} € / Monat</span>
                </div>
            </div>
        @endif

        {{-- Kontext-Tabs: die Linsen dieser Perspektive --}}
        @php $tabs = ['overview' => 'Übersicht', 'movers' => 'Bewegung', 'urls' => 'URLs', 'cannibalization' => 'Überschneidungen', 'competitors' => 'Wettbewerber', 'recommendations' => 'Empfehlungen', 'clusters' => 'Cluster']; @endphp
        <x-nx-tabs class="mb-6">
            @foreach($tabs as $key => $label)
                <x-nx-tab :active="$tab === $key" wire:click="$set('tab', '{{ $key }}')">{{ $label }}</x-nx-tab>
            @endforeach
        </x-nx-tabs>

        {{-- ============ ÜBERSICHT ============ --}}
        @if($tab === 'overview')
            @if($setup)
                {{-- AUFBAU-MODUS: geführte Startstrecke für einen Kunden bei Null (Reife-Vorgabe) --}}
                <x-nx-section icon="heroicon-o-rocket-launch" title="Aufbau" hint="startet bei Null" class="mb-6">
                    @if($notice)
                        <div class="mb-3 text-xs text-[color:var(--nx-success)]">{{ $notice }}</div>
                    @endif
                    <x-nx-card>
                        <p class="text-[13px] text-[color:var(--nx-muted)] mb-4">
                            <span class="font-medium text-[color:var(--nx-text)]">{{ $setup['name'] }}</span> hat noch keinen SEO-Fußabdruck. Das Umfeld rankt für
                            <span class="font-medium text-[color:var(--nx-text)]">{{ number_format($setup['pool_count']) }} Keywords</span>
                            ({{ number_format($setup['pool_volume']) }} Suchvolumen). So kommst du in den Betrieb:
                        </p>

                        <div class="space-y-2">
                            {{-- 1. Eigene Seite --}}
                            <div class="flex items-start gap-2">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 shrink-0 mt-0.5', ['style' => 'color: '.($setup['own_url']['done'] ? 'var(--nx-success)' : 'var(--nx-faint)')])
                                <div class="text-sm text-[color:var(--nx-text)]">Eigene Seite aufgehängt <span class="text-[color:var(--nx-faint)] tabular-nums">· {{ $setup['own_url']['count'] }}</span></div>
                            </div>

                            {{-- 2. Wettbewerber — die Vorgabe --}}
                            <div class="flex items-start gap-2">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 shrink-0 mt-0.5', ['style' => 'color: '.($setup['competitors']['ideal_done'] ? 'var(--nx-success)' : ($setup['competitors']['done'] ? 'var(--nx-info)' : 'var(--nx-faint)'))])
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm text-[color:var(--nx-text)]">Wettbewerber
                                        <span class="tabular-nums font-medium">{{ $setup['competitors']['count'] }}/{{ $setup['competitors']['ideal'] }}</span>
                                        @unless($setup['competitors']['done'])<span class="text-[color:var(--nx-danger)] text-xs">· min. {{ $setup['competitors']['min'] }} nötig</span>@endunless
                                    </div>
                                    <div class="mt-1 h-1.5 w-full max-w-[320px] rounded-full overflow-hidden" style="background: var(--nx-line)">
                                        <div class="h-full rounded-full" style="width: {{ min(100, (int) round($setup['competitors']['count'] / max(1, $setup['competitors']['ideal']) * 100)) }}%; background: {{ $setup['competitors']['ideal_done'] ? 'var(--nx-success)' : 'var(--nx-info)' }}"></div>
                                    </div>
                                    @unless($setup['competitors']['ideal_done'])
                                        <div class="mt-2 text-xs text-[color:var(--nx-muted)]">Für ein robustes Bild {{ $setup['competitors']['done'] ? 'wäre noch einer ideal' : 'fehlt noch mind. einer' }}. Weitere ergänzen:</div>
                                        <form wire:submit="addCompetitor" class="mt-1.5 flex items-center gap-2">
                                            <input type="url" wire:model="newCompetitorUrl" placeholder="https://wettbewerber.de" class="flex-1 max-w-[320px] border border-[color:var(--nx-line)] rounded-md px-2 py-1 text-xs bg-transparent text-[color:var(--nx-text)]">
                                            <x-nx-button type="submit" size="sm" variant="secondary">Hinzufügen</x-nx-button>
                                        </form>
                                    @endunless
                                </div>
                            </div>

                            {{-- 3. Ziel-Keywords säen --}}
                            <div class="flex items-start gap-2">
                                @svg('heroicon-o-check-circle', 'w-4 h-4 shrink-0 mt-0.5', ['style' => 'color: var(--nx-faint)'])
                                <div class="text-sm text-[color:var(--nx-text)]">Ziel-Keywords säen
                                    <span class="block text-xs text-[color:var(--nx-muted)]">{{ number_format($setup['pool_count']) }} Keywords im Umfeld verfügbar — nächster Baustein.</span>
                                </div>
                            </div>
                        </div>

                        @if($setup['pool_top']->isNotEmpty())
                            <div class="mt-4 pt-4 border-t" style="border-color: var(--nx-line)">
                                <div class="text-[10px] uppercase tracking-wide text-[color:var(--nx-faint)] mb-2">Stärkste Keywords im Umfeld</div>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($setup['pool_top'] as $kw)
                                        <span class="inline-flex items-center gap-1 rounded-md border px-2 py-0.5 text-xs text-[color:var(--nx-muted)]" style="border-color: var(--nx-line)">
                                            {{ $kw->keyword }}
                                            <span class="tabular-nums text-[color:var(--nx-faint)]">{{ number_format($kw->search_volume) }}</span>
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </x-nx-card>
                </x-nx-section>
            @else
            {{-- WAS JETZT — der Held: priorisierte Aktionen dieses Kunden --}}
            <x-nx-section icon="heroicon-o-bolt" title="Was jetzt" :hint="$openRecCount ? $openRecCount.' offen' : null" class="mb-6">
                @if($topRecommendations->isNotEmpty())
                    <x-nx-card flush>
                        <ul class="divide-y divide-[color:var(--nx-line)]">
                            @foreach($topRecommendations as $rec)
                                @php
                                    $sev = strtolower($rec->severity ?? '');
                                    $sevVariant = in_array($sev, ['critical','high','error']) ? 'danger' : ($sev === 'warning' ? 'warning' : ($sev === 'watch' ? 'info' : 'neutral'));
                                @endphp
                                <x-nx-list-item :title="$rec->title" :subtitle="$rec->description">
                                    <x-slot name="trailing">
                                        @if($rec->url)
                                            <a href="{{ route('seo.urls.show', $rec->url->id) }}" wire:navigate class="hidden sm:block max-w-[320px] truncate text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">{{ $rec->url->domain }}{{ $rec->url->path && $rec->url->path !== '/' ? $rec->url->path : '' }}</a>
                                        @endif
                                        <x-nx-badge :variant="$sevVariant">{{ $sev ?: 'info' }}</x-nx-badge>
                                        <x-nx-button variant="ghost" size="sm" wire:click="resolveSignal({{ $rec->id }})">Erledigt</x-nx-button>
                                    </x-slot>
                                </x-nx-list-item>
                            @endforeach
                        </ul>
                    </x-nx-card>
                    @if($openRecCount > $topRecommendations->count())
                        <button wire:click="$set('tab', 'recommendations')" class="text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Alle {{ $openRecCount }} Empfehlungen →</button>
                    @endif
                @else
                    <x-nx-empty icon="heroicon-o-check-circle">Keine offenen Empfehlungen — alles abgearbeitet.</x-nx-empty>
                @endif
            </x-nx-section>
            @endif

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

            {{-- In Arbeit: eigene Seiten im Engagement (unser Mandat) --}}
            <x-nx-section title="In Arbeit" hint="im Engagement" class="mb-6">
                <x-slot name="action"><x-nx-button variant="ghost" size="sm" wire:click="$set('tab','urls')">Alle URLs</x-nx-button></x-slot>
                @if($ownWorked->isNotEmpty())
                    <x-nx-card flush>
                        <ul class="divide-y divide-[color:var(--nx-line)]">
                            @foreach($ownWorked->take(8) as $url)
                                <x-nx-list-item :title="$url->display_label" :meta="'Sicht. '.number_format($url->visibility_score, 0)" :href="route('seo.urls.show', $url->id)" />
                            @endforeach
                        </ul>
                    </x-nx-card>
                @else
                    <x-nx-empty>Noch keine Seite im Engagement — hänge die Kunden-URL an eine Initiative im DIGITAL-Engagement, dann wird sie bearbeitet (Signale, Aufgaben).</x-nx-empty>
                @endif
            </x-nx-section>

            {{-- Umwelt: beobachtete eigene Seiten (kein aktives Mandat) --}}
            @if($ownEnvironment->isNotEmpty())
                <x-nx-section title="Umwelt" hint="beobachtet" class="mb-6">
                    <x-nx-card flush>
                        <ul class="divide-y divide-[color:var(--nx-line)]">
                            @foreach($ownEnvironment->take(8) as $url)
                                <x-nx-list-item :title="$url->display_label" subtitle="beobachtet — kein aktives Mandat" :meta="'Sicht. '.number_format($url->visibility_score, 0)" :href="route('seo.urls.show', $url->id)" />
                            @endforeach
                        </ul>
                    </x-nx-card>
                </x-nx-section>
            @endif

            {{-- Wettbewerber-Streifen (klar getrennt von den eigenen) --}}
            @if($topCompetitors->isNotEmpty())
                <x-nx-section title="Wettbewerber" :hint="(string) $kpis['competitors']" class="mb-6">
                    <x-slot name="action"><x-nx-button variant="ghost" size="sm" wire:click="$set('tab','competitors')">Alle</x-nx-button></x-slot>
                    <div class="flex flex-wrap gap-2">
                        @foreach($topCompetitors as $c)
                            <x-nx-badge variant="warning" dot>{{ $c->domain }} · {{ number_format($c->visibility_score, 0) }}</x-nx-badge>
                        @endforeach
                    </div>
                </x-nx-section>
            @endif
        @endif

        {{-- ============ URLS ============ --}}
        @if($tab === 'urls')
            @if($notice)
                <x-nx-callout variant="success" class="mb-4">{{ $notice }}</x-nx-callout>
            @endif

            @if(in_array($mode, ['unassigned', 'source']))
                {{-- Ablage/Quelle: Klassifizieren-Arbeitsplatz (auswählen → zuweisen / Wettbewerber) --}}
                <x-nx-section title="URLs klassifizieren">
                    @if(!empty($selected))
                        <x-slot name="action">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs tabular-nums text-[color:var(--nx-muted)]">{{ count($selected) }} ausgewählt</span>
                                <select wire:model="assignNodeId" class="rounded-[6px] border border-[color:var(--nx-line-strong)] bg-[color:var(--nx-surface)] px-2 py-1 text-xs text-[color:var(--nx-text)]">
                                    <option value="">Kontext wählen…</option>
                                    @foreach($availableNodes as $node)
                                        <option value="{{ $node['id'] }}">{{ $node['name'] }}</option>
                                    @endforeach
                                </select>
                                <x-nx-button variant="primary" size="sm" wire:click="assignSelected(false)" :disabled="!$assignNodeId">Zuweisen</x-nx-button>
                                <x-nx-button size="sm" wire:click="assignSelected(true)" :disabled="!$assignNodeId">Als Wettbewerber</x-nx-button>
                                <x-nx-button variant="ghost" size="sm" wire:click="markCompetitor">Nur Wettbewerber</x-nx-button>
                                <x-nx-button variant="ghost" size="sm" wire:click="clearSelection">×</x-nx-button>
                            </div>
                        </x-slot>
                    @endif

                    @if($urls->isNotEmpty())
                        <x-nx-card flush>
                            <x-nx-table>
                                <x-nx-table-header>
                                    <x-nx-table-header-cell class="w-8">
                                        <button wire:click="selectAll({{ $urls->pluck('id')->toJson() }})" title="Alle auswählen">☐</button>
                                    </x-nx-table-header-cell>
                                    <x-nx-table-header-cell>URL</x-nx-table-header-cell>
                                    <x-nx-table-header-cell align="right">Keywords</x-nx-table-header-cell>
                                    <x-nx-table-header-cell align="right">Suchvolumen</x-nx-table-header-cell>
                                    <x-nx-table-header-cell align="right">Sichtbarkeit</x-nx-table-header-cell>
                                </x-nx-table-header>
                                <x-nx-table-body>
                                    @foreach($urls as $url)
                                        <x-nx-table-row>
                                            <x-nx-table-cell>
                                                <input type="checkbox" wire:model.live="selected" value="{{ $url->id }}" class="rounded border-[color:var(--nx-line-strong)]">
                                            </x-nx-table-cell>
                                            <x-nx-table-cell>
                                                <span class="flex items-center gap-2">
                                                    @if(!$url->is_own)
                                                        <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-warning)] shrink-0" title="Wettbewerber"></span>
                                                    @endif
                                                    <a href="{{ route('seo.urls.show', $url->id) }}" wire:navigate class="block truncate max-w-[320px] font-medium text-[color:var(--nx-text)] hover:underline">{{ $url->display_label }}</a>
                                                    @if(!empty($url->provenance_key) && !in_array($url->provenance_key, ['seo', 'competitor']))
                                                        @include('seo::partials.url-provenance-badge', ['key' => $url->provenance_key])
                                                    @endif
                                                </span>
                                            </x-nx-table-cell>
                                            <x-nx-table-cell align="right"><span class="tabular-nums text-[color:var(--nx-muted)]">{{ number_format($url->keyword_count) }}</span></x-nx-table-cell>
                                            <x-nx-table-cell align="right"><span class="tabular-nums text-[color:var(--nx-muted)]">{{ number_format($url->total_search_volume) }}</span></x-nx-table-cell>
                                            <x-nx-table-cell align="right"><span class="tabular-nums font-medium">{{ number_format($url->visibility_score, 0) }}</span></x-nx-table-cell>
                                        </x-nx-table-row>
                                    @endforeach
                                </x-nx-table-body>
                            </x-nx-table>
                        </x-nx-card>
                    @else
                        <x-nx-empty>Keine URLs in dieser Ablage.</x-nx-empty>
                    @endif
                </x-nx-section>
            @else
                {{-- Kunden-Sicht: „Deine Seiten" — Portfolio mit Gesundheit + Arbeit --}}
                <x-nx-section title="Deine Seiten">
                    @if($urlsRich->isNotEmpty())
                        <x-nx-card flush>
                            <ul class="divide-y divide-[color:var(--nx-line)]">
                                @foreach($urlsRich as $u)
                                    @php
                                        $fresh = $u->freshness_status;
                                        $freshDot = match($fresh) { 'fresh' => 'var(--nx-success)', 'due_soon' => 'var(--nx-warning)', 'overdue' => 'var(--nx-danger)', default => 'var(--nx-faint)' };
                                        $freshLabel = match($fresh) { 'fresh' => 'frisch', 'due_soon' => 'bald fällig', 'overdue' => 'überfällig', default => 'nie' };
                                        $sub = 'Sicht. '.number_format($u->visibility_score, 0).' · '.number_format($u->keyword_count).' KW';
                                        if ($u->vis_delta !== null && $u->vis_delta !== 0) { $sub .= ' · '.($u->vis_delta > 0 ? '▲ +' : '▼ ').number_format($u->vis_delta); }
                                    @endphp
                                    <x-nx-list-item :title="$u->display_label" :subtitle="$sub" :href="route('seo.urls.show', $u->id)">
                                        <x-slot name="leading"><span class="w-2 h-2 rounded-full shrink-0" style="background: {{ $freshDot }}" title="{{ $freshLabel }}"></span></x-slot>
                                        <x-slot name="trailing">
                                            @if($u->open_recs > 0)
                                                <x-nx-badge variant="info">{{ $u->open_recs }} offen</x-nx-badge>
                                            @endif
                                            <span class="text-xs text-[color:var(--nx-faint)]">{{ $freshLabel }}</span>
                                        </x-slot>
                                    </x-nx-list-item>
                                @endforeach
                            </ul>
                        </x-nx-card>
                    @else
                        <x-nx-empty>Keine eigenen Seiten in dieser Perspektive.</x-nx-empty>
                    @endif
                </x-nx-section>
            @endif
        @endif

        {{-- ============ WETTBEWERBER ============ --}}
        @if($tab === 'competitors')
            @if($competitors->isNotEmpty())
                <p class="mb-3 text-xs text-[color:var(--nx-muted)]">Domains, die auf denselben Keywords ranken wie die eigenen URLs — die reale Konkurrenz um diese Themen.</p>
                <x-nx-card flush>
                    <ul class="divide-y divide-[color:var(--nx-line)]">
                        @foreach($competitors as $c)
                            <x-nx-list-item :title="$c->domain" :subtitle="$c->url_count.' URLs · '.number_format($c->total_keywords).' KW'" :meta="'Ø Sicht. '.number_format((float) $c->avg_visibility, 0)">
                                <x-slot name="leading"><span class="w-1.5 h-1.5 rounded-full bg-[color:var(--nx-warning)]"></span></x-slot>
                            </x-nx-list-item>
                        @endforeach
                    </ul>
                </x-nx-card>
            @else
                <x-nx-empty>Keine Wettbewerber für diese Perspektive — sie entstehen aus dem Keyword-Überlapp mit den eigenen URLs, sobald Rankings gemessen sind.</x-nx-empty>
            @endif
        @endif

        {{-- ============ EMPFEHLUNGEN ============ --}}
        @if($tab === 'recommendations')
            @if($recommendations->isNotEmpty())
                <x-nx-card flush>
                    <ul class="divide-y divide-[color:var(--nx-line)]">
                        @foreach($recommendations as $rec)
                            @php
                                $sev = strtolower($rec->severity ?? '');
                                $sevVariant = in_array($sev, ['critical','high','error']) ? 'danger' : ($sev === 'warning' ? 'warning' : ($sev === 'watch' ? 'info' : 'neutral'));
                            @endphp
                            <x-nx-list-item :title="$rec->title" :subtitle="$rec->description">
                                <x-slot name="trailing">
                                    @if($rec->url)
                                        <a href="{{ route('seo.urls.show', $rec->url->id) }}" wire:navigate class="hidden sm:block max-w-[320px] truncate text-xs text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">{{ $rec->url->domain }}{{ $rec->url->path && $rec->url->path !== '/' ? $rec->url->path : '' }}</a>
                                    @endif
                                    <x-nx-badge :variant="$sevVariant">{{ $sev ?: 'info' }}</x-nx-badge>
                                    <x-nx-button variant="ghost" size="sm" wire:click="resolveSignal({{ $rec->id }})">Erledigt</x-nx-button>
                                </x-slot>
                            </x-nx-list-item>
                        @endforeach
                    </ul>
                </x-nx-card>
            @else
                <x-nx-empty icon="heroicon-o-check-circle">Keine offenen Empfehlungen für diese Perspektive.</x-nx-empty>
            @endif
        @endif

        {{-- ============ CLUSTER ============ --}}
        @if($tab === 'clusters')
            @if($clusters->isNotEmpty())
                <x-nx-card flush>
                    <ul class="divide-y divide-[color:var(--nx-line)]">
                        @foreach($clusters as $cluster)
                            @php
                                $st = $cluster->status ?? 'candidate';
                                $stVariant = match($st) { 'active' => 'accent', 'monitored' => 'success', 'stalled' => 'warning', 'archived' => 'neutral', default => 'info' };
                                $stLabel = match($st) { 'candidate' => 'Kandidat', 'active' => 'Aktiv', 'monitored' => 'Beobachtet', 'stalled' => 'Stillstand', 'archived' => 'Archiv', default => $st };
                            @endphp
                            <x-nx-list-item :title="$cluster->name" :meta="$cluster->keyword_count.' KW · Sicht. '.number_format($cluster->visibility, 0)" :href="route('seo.clusters.show', $cluster)">
                                <x-slot name="leading"><span class="w-2.5 h-2.5 rounded-full" style="background: {{ $cluster->color ?: 'var(--nx-faint)' }}"></span></x-slot>
                                <x-slot name="trailing">
                                    @if($cluster->penetration !== null)
                                        <span class="text-xs tabular-nums text-[color:var(--nx-muted)]">Durchdr. {{ $cluster->penetration }}</span>
                                    @endif
                                    <x-nx-badge :variant="$stVariant">{{ $stLabel }}</x-nx-badge>
                                </x-slot>
                            </x-nx-list-item>
                        @endforeach
                    </ul>
                </x-nx-card>
            @else
                <x-nx-empty icon="heroicon-o-squares-2x2">Noch keine Cluster für diese Perspektive. Cluster entstehen über die Discovery (Keywords-Tab einer eigenen URL).</x-nx-empty>
            @endif
        @endif

        {{-- ============ ÜBERSCHNEIDUNGEN (Kannibalisierung) ============ --}}
        @if($tab === 'cannibalization')
            @if(!empty($cannibalization))
                <p class="mb-3 text-xs text-[color:var(--nx-muted)]">Keywords, für die <b>mehrere deiner eigenen Seiten</b> ranken — sie konkurrieren miteinander und teilen die Autorität. Konsolidieren oder klar differenzieren.</p>
                <div class="space-y-3">
                    @foreach($cannibalization as $c)
                        <x-nx-card>
                            <div class="mb-2 flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-[color:var(--nx-text)]">{{ $c['keyword'] }}</span>
                                <span class="shrink-0 text-xs tabular-nums text-[color:var(--nx-muted)]">{{ number_format($c['search_volume']) }} Vol. · {{ count($c['urls']) }} Seiten</span>
                            </div>
                            <ul class="divide-y divide-[color:var(--nx-line)]">
                                @foreach($c['urls'] as $u)
                                    <li class="flex items-center justify-between gap-3 py-1.5 text-xs">
                                        <a href="{{ route('seo.urls.show', $u['url_id']) }}" wire:navigate class="truncate text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:underline">{{ $u['url'] }}</a>
                                        @include('seo::partials.position-badge', ['position' => $u['position'], 'change' => null])
                                    </li>
                                @endforeach
                            </ul>
                        </x-nx-card>
                    @endforeach
                </div>
            @else
                <x-nx-empty icon="heroicon-o-arrows-pointing-in">Keine Überschneidungen — aktuell rankt kein Keyword mit mehreren deiner eigenen Seiten. (Braucht gemessene Rankings.)</x-nx-empty>
            @endif
        @endif

        {{-- ============ BEWEGUNG (Movers) ============ --}}
        @if($tab === 'movers')
            @if($moverGainers->isEmpty() && $moverLosers->isEmpty())
                <x-nx-empty icon="heroicon-o-arrow-trending-up">Noch keine Bewegung — Positions-Deltas erscheinen ab der zweiten Messung (wöchentliche Rankings). Die Basis-Messung läuft.</x-nx-empty>
            @else
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <x-nx-section icon="heroicon-o-arrow-trending-up" title="Gewinner" :hint="(string) $moverGainers->count()">
                        @if($moverGainers->isNotEmpty())
                            <x-nx-card flush>
                                <ul class="divide-y divide-[color:var(--nx-line)]">
                                    @foreach($moverGainers as $m)
                                        <x-nx-list-item :title="$m->keyword" :subtitle="'Pos. '.$m->previous_position.' → '.$m->position">
                                            <x-slot name="trailing"><x-nx-badge variant="success">▲ {{ $m->delta }}</x-nx-badge></x-slot>
                                        </x-nx-list-item>
                                    @endforeach
                                </ul>
                            </x-nx-card>
                        @else
                            <x-nx-empty>Keine Aufsteiger im aktuellen Zeitraum.</x-nx-empty>
                        @endif
                    </x-nx-section>

                    <x-nx-section icon="heroicon-o-arrow-trending-down" title="Verlierer" :hint="(string) $moverLosers->count()">
                        @if($moverLosers->isNotEmpty())
                            <x-nx-card flush>
                                <ul class="divide-y divide-[color:var(--nx-line)]">
                                    @foreach($moverLosers as $m)
                                        <x-nx-list-item :title="$m->keyword" :subtitle="'Pos. '.$m->previous_position.' → '.$m->position">
                                            <x-slot name="trailing"><x-nx-badge variant="danger">▼ {{ abs($m->delta) }}</x-nx-badge></x-slot>
                                        </x-nx-list-item>
                                    @endforeach
                                </ul>
                            </x-nx-card>
                        @else
                            <x-nx-empty>Keine Absteiger im aktuellen Zeitraum.</x-nx-empty>
                        @endif
                    </x-nx-section>
                </div>
            @endif
        @endif

    </x-ui-page-container>
</x-ui-page>
