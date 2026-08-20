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
                        @if($isOrphan)
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-100" title="Ohne Zuhause — in keinem Wirkungsraum, keiner Liste, keinem Modul, an keinem Org-Knoten">⚠ verwaist</span>
                        @endif
                        <span>{{ $seoUrl->is_own ? 'Eigene URL' : 'Wettbewerber' }}</span>
                        <span>&middot;</span>
                        <span>Priorität: {{ $seoUrl->priority }}</span>
                        <span>&middot;</span>
                        @include('seo::partials.freshness-badge', ['url' => $seoUrl, 'showNext' => true])
                        @if($childUrls->isNotEmpty())
                            <span>&middot;</span>
                            <span>{{ $childUrls->count() }} Unterseiten</span>
                        @endif
                    </div>
                </div>
                @include('seo::partials.url-status-badge', ['status' => $seoUrl->status, 'httpStatus' => $seoUrl->http_status])
            </div>

            {{-- SEO-Ziel: die deklarierten Dimensionen dieser Seite (nur eigene URLs).
                 Bearbeiten öffnet die eigenständige UrlSeoTarget-Modal-Komponente. --}}
            @if($seoUrl->is_own)
                @php
                    $dimsByType = $seoUrl->dimensions->groupBy('dimension');
                    $dimCatalog = \Platform\Seo\Models\SeoUrlDimension::catalog();
                @endphp
                <div class="flex items-start gap-2 flex-wrap">
                    <span class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">SEO-Ziel</span>
                    <div class="flex-1 flex items-center gap-2 flex-wrap">
                        @foreach($dimCatalog as $key => $cfg)
                            @if(($group = $dimsByType->get($key)) && $group->isNotEmpty())
                                <span class="inline-flex items-center gap-1 text-[11px] text-gray-600">
                                    <span class="text-gray-400">{{ $cfg['label'] ?? $key }}:</span>
                                    @foreach($group as $d)
                                        <span class="px-1.5 py-0.5 rounded {{ $key === 'geo' ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-700' }}">{{ $key === 'geo' ? '📍 ' : '' }}{{ $d->value }}</span>
                                    @endforeach
                                </span>
                            @endif
                        @endforeach
                        @if($dimsByType->isEmpty())
                            <span class="text-[11px] text-gray-400">noch nicht definiert</span>
                        @endif
                        <button wire:click="$dispatch('open-url-target', { urlId: {{ $seoUrl->id }} })"
                                class="text-[11px] text-indigo-600 hover:underline">{{ $dimsByType->isEmpty() ? 'definieren' : 'bearbeiten' }} →</button>
                    </div>
                </div>
                <livewire:seo.url-seo-target />
            @endif

            {{-- Kontext: URL an Organisations-Knoten hängen (lose in Organization verlinkt) --}}
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

            {{-- URL-Steckbrief — das erklärte SOLL (Seitentyp/Intent/Funnel/Ziel/Fokus).
                 KI schlägt vor, Mensch bestätigt. Block-Direktive (Datei-Konvention). --}}
            @php
                $sbCfg = config('seo.steckbrief');
                $sbTypes = collect($sbCfg['page_types'])->map(fn ($v) => $v['label']);
                $sbIntents = $sbCfg['intents'];
                $sbFunnels = $sbCfg['funnel_stages'];
                $sbObjectives = $sbCfg['objectives'];
                $sbFilled = ! empty($steckbrief['page_type']) || ! empty($steckbrief['target_intent']);
                $sbConfirmed = $sbFilled && ! empty($sbConfirmedAt);
                $sbTypeLabel = $steckbrief['page_type'] ? ($sbTypes[$steckbrief['page_type']] ?? $steckbrief['page_type']) : null;
                $sbIntentLabel = $steckbrief['target_intent'] ? ($sbIntents[$steckbrief['target_intent']] ?? null) : null;
            @endphp
            <div x-data="{ open: {{ $sbFilled || $sbDirty ? 'true' : 'false' }} }" class="bg-white rounded-lg border {{ $sbFilled && ! $sbConfirmed ? 'border-amber-200' : 'border-gray-200' }}">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left">
                    <div class="flex items-center gap-2 text-[13px] flex-wrap">
                        <span class="font-semibold text-gray-700">Steckbrief</span>
                        <span class="text-gray-300">·</span>
                        @if($sbFilled)
                            <span class="text-gray-600">{{ $sbTypeLabel }}@if($sbIntentLabel) <span class="text-gray-300">·</span> {{ $sbIntentLabel }}@endif</span>
                            @if($sbConfirmed)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-green-50 text-green-700 border border-green-100">bestätigt</span>
                            @else
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-100">KI-Vorschlag · unbestätigt</span>
                            @endif
                        @else
                            <span class="text-gray-400 italic">noch nicht ausgefüllt — das SOLL dieser Seite</span>
                        @endif
                    </div>
                    <span class="text-[11px] text-gray-400 shrink-0" x-text="open ? 'schließen ▴' : 'ansehen ▾'"></span>
                </button>

                <div x-show="open" style="display:none" class="px-4 pb-4 pt-4 border-t border-gray-100 space-y-4">
                    @if($sbError)
                        <div class="text-[12px] px-3 py-2 rounded bg-red-50 text-red-700 border border-red-100">{{ $sbError }}</div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div>
                            <label class="block text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Seitentyp</label>
                            <select wire:model="steckbrief.page_type" class="w-full text-[13px] border border-gray-300 rounded-md px-2 py-1.5 bg-white">
                                <option value="">—</option>
                                @foreach($sbTypes as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Ziel-Intent</label>
                            <select wire:model="steckbrief.target_intent" class="w-full text-[13px] border border-gray-300 rounded-md px-2 py-1.5 bg-white">
                                <option value="">—</option>
                                @foreach($sbIntents as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Funnel-Stufe</label>
                            <select wire:model="steckbrief.funnel_stage" class="w-full text-[13px] border border-gray-300 rounded-md px-2 py-1.5 bg-white">
                                <option value="">—</option>
                                @foreach($sbFunnels as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Seitenziel</label>
                            <select wire:model="steckbrief.page_objective" class="w-full text-[13px] border border-gray-300 rounded-md px-2 py-1.5 bg-white">
                                <option value="">—</option>
                                @foreach($sbObjectives as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Fokus-Thema</label>
                            <input type="text" wire:model="steckbrief.focus_keyword" placeholder="1 Keyword" class="w-full text-[13px] border border-gray-300 rounded-md px-2 py-1.5 bg-white" />
                        </div>
                    </div>

                    @if($sbRationale)
                        <div class="text-[12px] text-gray-500"><span class="font-medium text-gray-600">KI:</span> {{ $sbRationale }}</div>
                    @endif

                    <div class="flex items-center justify-between gap-3 flex-wrap">
                        <div class="flex items-center gap-2">
                            <button wire:click="proposeSteckbrief" wire:loading.attr="disabled" wire:target="proposeSteckbrief"
                                    class="text-[12px] px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:border-gray-300 disabled:opacity-40">
                                <span wire:loading.remove wire:target="proposeSteckbrief">✨ KI-Vorschlag</span>
                                <span wire:loading wire:target="proposeSteckbrief">leitet ab…</span>
                            </button>
                            <button wire:click="saveSteckbrief"
                                    class="text-[12px] px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">
                                {{ $sbConfirmed ? 'Speichern' : 'Bestätigen & speichern' }}
                            </button>
                        </div>
                        <div class="text-[11px] text-gray-400">
                            @if($sbConfirmed && $sbConfirmedAt)
                                {{ $sbSource === 'ai' ? 'KI-abgeleitet' : 'manuell' }} · bestätigt {{ \Illuminate\Support\Carbon::parse($sbConfirmedAt)->format('d.m.Y') }}
                            @elseif($sbFilled)
                                Vorschlag — bitte prüfen & bestätigen
                            @endif
                        </div>
                    </div>
                    <p class="text-[11px] text-gray-400">Das erklärte SOLL dieser Seite — die KI misst rankende Keywords dagegen (Intent-Abgleich), priorisiert nach Ziel und kann daraus schema.org-Markup erzeugen.</p>
                </div>
            </div>

            {{-- Antwort-Einheiten (v2) — der Seiteninhalt als atomare, zitierfähige Bausteine (je Entität ein Claim) --}}
            @if($seoUrl->is_own)
            <div x-data="{ open: {{ $answerUnits->isNotEmpty() ? 'true' : 'false' }} }" class="bg-white rounded-lg border border-gray-200">
                <button type="button" @click="open = !open" class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left">
                    <div class="flex items-center gap-2 text-[13px] flex-wrap">
                        <span class="font-semibold text-gray-700">Antwort-Einheiten</span>
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600 border border-indigo-100">v2</span>
                        <span class="text-gray-300">·</span>
                        @if($answerUnits->isNotEmpty())
                            <span class="text-gray-600">{{ $answerUnits->count() }} {{ $answerUnits->count() === 1 ? 'Baustein' : 'Bausteine' }}</span>
                        @else
                            <span class="text-gray-400 italic">noch nicht extrahiert — was die Seite autoritativ beantwortet</span>
                        @endif
                    </div>
                    <span class="text-[11px] text-gray-400 shrink-0" x-text="open ? 'schließen ▴' : 'ansehen ▾'"></span>
                </button>
                <div x-show="open" style="display:none" class="px-4 pb-4 pt-4 border-t border-gray-100 space-y-3">
                    @if($answerFlash)
                        <div class="text-[12px] px-3 py-2 rounded {{ str_starts_with($answerFlash, 'Fehler') ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-teal-50 text-teal-700 border border-teal-100' }}">{{ $answerFlash }}</div>
                    @endif

                    @if($answerUnits->isEmpty())
                        <p class="text-[12px] text-gray-500">Zerlegt den echten Seiteninhalt in atomare Antwort-Einheiten (je Entität/Frage ein Claim) — die Vergleichsbasis für Optimierung und KI-Zitat-Präsenz.</p>
                    @else
                        <div class="divide-y divide-gray-50">
                            @foreach($answerUnits as $au)
                                <div class="py-2">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-[13px] font-medium text-gray-800">{{ $au->entity->name ?? '—' }}</span>
                                        @if($au->entity && $au->entity->entity_type)<span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-gray-100 text-gray-500">{{ $au->entity->entity_type }}</span>@endif
                                        @if($au->schema_type)<span class="text-[9px] px-1 py-0.5 rounded bg-indigo-50 text-indigo-600">{{ $au->schema_type }}</span>@endif
                                    </div>
                                    <div class="text-[12px] text-gray-500 mt-0.5">{{ $au->claim }}</div>
                                    @if(!empty($presenceByUnit[$au->id]))
                                        <div class="flex items-center gap-1.5 mt-1">
                                            @if(!empty($presenceByUnit[$au->id]['serp']))
                                                <span class="text-[10px] px-1.5 py-0.5 rounded {{ $presenceByUnit[$au->id]['serp']->present ? 'bg-green-50 text-green-700 border border-green-100' : 'bg-gray-100 text-gray-400' }}" title="Klassische Suche">SERP {{ $presenceByUnit[$au->id]['serp']->present ? '#'.($presenceByUnit[$au->id]['serp']->position ?? '?') : '—' }}</span>
                                            @endif
                                            @if(!empty($presenceByUnit[$au->id]['ai_overview']))
                                                <span class="text-[10px] px-1.5 py-0.5 rounded {{ $presenceByUnit[$au->id]['ai_overview']->cited ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-gray-100 text-gray-400' }}" title="AI-Sichtbarkeit (llm_mentions)">AI {{ $presenceByUnit[$au->id]['ai_overview']->cited ? 'zitiert' : '—' }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="flex items-center gap-2 flex-wrap">
                        <button wire:click="extractAnswerUnits" wire:loading.attr="disabled" wire:target="extractAnswerUnits"
                                class="text-[12px] px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:border-gray-300 disabled:opacity-40">
                            <span wire:loading.remove wire:target="extractAnswerUnits">✨ {{ $answerUnits->isEmpty() ? 'Extrahieren' : 'Neu extrahieren' }}</span>
                            <span wire:loading wire:target="extractAnswerUnits">holt Seite + KI…</span>
                        </button>
                        @if($answerUnits->isNotEmpty())
                            <button wire:click="checkPresence" wire:loading.attr="disabled" wire:target="checkPresence"
                                    class="text-[12px] px-3 py-1.5 rounded-md border border-gray-200 text-gray-700 hover:border-gray-300 disabled:opacity-40">
                                <span wire:loading.remove wire:target="checkPresence">📡 Presence prüfen</span>
                                <span wire:loading wire:target="checkPresence">misst…</span>
                            </button>
                        @endif
                    </div>
                    <p class="text-[11px] text-gray-400">Holt die echte Seite (JSON-LD + Text), die KI leitet je Entität einen Claim ab. Füllt die v2-Spine (Entität → Antwort-Einheit → Präsenz).</p>
                </div>
            </div>
            @endif

            {{-- Stats --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-4">
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
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $aggBacklinks === null ? '—' : number_format($aggBacklinks) }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">On-Page</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $onPageScore ?? '—' }}</div>
                </div>
                <div class="bg-white rounded-lg border border-gray-200 p-4">
                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Traffic (30T)</div>
                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $aggVisitors === null ? '—' : number_format($aggVisitors) }}</div>
                    @if($seoUrl->traffic_fetched_at)
                        <div class="text-[10px] text-gray-400 mt-1">Plausible · {{ $seoUrl->traffic_fetched_at->format('d.m.Y') }}</div>
                    @endif
                </div>
            </div>

            {{-- Datenstatus — Sammlung (Profil/Kosten/Boost) + Aktualität, zusammengeklappt.
                 Lesend zuerst; die Schalter bleiben erreichbar, ziehen aber laut Zielbild in
                 den Wirkungsraum. Alpine statt <details>, damit Livewire-Re-Renders es offen lassen. --}}
            <div x-data="{ open: false }" class="bg-white rounded-lg border border-gray-200">
                <button type="button" @click="open = !open"
                        class="w-full flex items-center justify-between gap-3 px-4 py-3 text-left">
                    <div class="flex items-center gap-2 text-[13px] flex-wrap">
                        <span class="font-semibold text-gray-700">Datenstatus</span>
                        <span class="text-gray-300">·</span>
                        <span class="text-gray-500">Profil <span class="font-medium text-gray-700">{{ ucfirst($effectiveProfile) }}</span></span>
                        <span class="text-gray-300">·</span>
                        <span class="text-gray-500 tabular-nums">{{ number_format($profileMonthlyCents / 100, 2, ',', '.') }} € / Monat</span>
                    </div>
                    <span class="text-[11px] text-gray-400 shrink-0" x-text="open ? 'schließen ▴' : 'Sammlung & Aktualität ▾'"></span>
                </button>

                <div x-show="open" style="display:none" class="px-4 pb-4 pt-4 border-t border-gray-100 space-y-4">
                    {{-- Aktualität pro Collector --}}
                    @include('seo::partials.data-freshness-panel', ['url' => $seoUrl])

                    {{-- Sammlung — Profil + Boost --}}
                    <div>
                        <div class="text-[11px] text-gray-400 mb-1.5">Sammlung <span class="text-gray-300">·</span> <span class="italic">Einstellungen ziehen laut Zielbild in den Wirkungsraum</span></div>
                        <div class="flex items-center gap-1 bg-gray-100 rounded-lg p-0.5 w-fit">
                            @foreach($availableProfiles as $p)
                                <button wire:click="setProfile('{{ $p }}')"
                                        class="px-3 py-1.5 text-[12px] rounded-md transition-colors {{ $effectiveProfile === $p ? 'bg-white text-gray-900 font-medium shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
                                    {{ ucfirst($p) }}
                                </button>
                            @endforeach
                        </div>

                        <div class="flex items-start justify-between gap-4 mt-3 flex-wrap">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                @forelse($profileCostBreakdown as $line)
                                    <span class="text-[11px] px-2 py-0.5 bg-gray-50 text-gray-600 border border-gray-200 rounded">
                                        {{ $line['collector'] }} · {{ $line['monthly_cents'] > 0 ? number_format($line['monthly_cents']/100, 2, ',', '.').' €' : 'gratis' }}
                                    </span>
                                @empty
                                    <span class="text-[11px] text-gray-400">Profil „Aus" — es werden keine Daten geholt.</span>
                                @endforelse
                            </div>

                            <div class="flex items-center gap-2">
                                @if($seoUrl->isBoostActive())
                                    <span class="text-[11px] px-2 py-0.5 bg-amber-50 text-amber-700 border border-amber-100 rounded">Boost bis {{ $seoUrl->boost_until->format('d.m.') }}</span>
                                    <button wire:click="setBoost(0)" class="text-[11px] text-gray-500 hover:text-gray-700">beenden</button>
                                @else
                                    <button wire:click="setBoost({{ (int) config('seo.boost.default_days', 14) }})"
                                            class="text-[11px] px-2 py-1 rounded border border-gray-200 text-gray-600 hover:border-gray-300">
                                        ⚡ Boost ({{ (int) config('seo.boost.default_days', 14) }} T täglich SERP)
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Tabs — 4 primaere Datensichten + „Mehr" fuer die selteneren (Backlinks/On-Page/AI/Beziehungen).
                 Block-Direktive (Datei-Konvention: keine inline-Variante, sonst Raw-Block-ParseError). --}}
            @php
                $secondaryTabs = ['backlinks' => 'Backlinks', 'onpage' => 'On-Page', 'ai' => 'AI-Sichtbarkeit', 'relationships' => 'Beziehungen'];
                $inSecondary = in_array($activeTab, array_keys($secondaryTabs), true);
            @endphp
            <div>
                <div class="flex items-center gap-1 border-b border-gray-200 mb-6">
                    @foreach(['keywords' => 'Keywords', 'rankings' => 'Rankings', 'gsc' => 'GSC', 'plausible' => 'Plausible'] as $tab => $label)
                        <button wire:click="setTab('{{ $tab }}')"
                                class="px-4 py-3 text-[13px] font-medium transition-colors {{ $activeTab === $tab ? 'text-[#166EE1] border-b-2 border-[#166EE1]' : 'text-gray-500 hover:text-gray-700' }}">
                            {{ $label }}
                        </button>
                    @endforeach

                    {{-- Mehr — seltenere Datensichten, weggeklappt (nicht gelöscht) --}}
                    <div x-data="{ open: false }" class="relative">
                        <button type="button" @click="open = !open"
                                class="px-4 py-3 text-[13px] font-medium transition-colors inline-flex items-center gap-1 {{ $inSecondary ? 'text-[#166EE1] border-b-2 border-[#166EE1]' : 'text-gray-500 hover:text-gray-700' }}">
                            {{ $inSecondary ? $secondaryTabs[$activeTab] : 'Mehr' }} <span class="text-[10px]">▾</span>
                        </button>
                        <div x-show="open" @click.outside="open = false" style="display:none"
                             class="absolute left-0 top-full z-20 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-[170px]">
                            @foreach($secondaryTabs as $tab => $label)
                                <button wire:click="setTab('{{ $tab }}')" @click="open = false"
                                        class="block w-full text-left px-3 py-1.5 text-[13px] {{ $activeTab === $tab ? 'text-[#166EE1] font-medium bg-blue-50/50' : 'text-gray-600 hover:bg-gray-50' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Keywords Tab — KWFinder-style split panel --}}
                @if($activeTab === 'keywords')
                    {{-- Geclustert wird nur im Wirkungsraum (Station „Ordnen"), nicht je URL. --}}
                    @if($seoUrl->is_own && $scope['clusters']->isNotEmpty())
                        {{-- Nur lesend: an welchen Cluster-Themen diese URL beteiligt ist. --}}
                        @include('seo::partials.scope-penetration', ['clusters' => $scope['clusters'], 'coverage' => $scope['coverage']])
                    @endif

                    @if($hasKeywords)
                        {{-- Filter-/Sortierleiste --}}
                        <div class="flex flex-wrap items-center gap-2 mb-3">
                            <select wire:model.live="keywordIntent" class="text-[12px] border border-gray-200 rounded-md px-2 py-1.5 bg-white text-gray-600">
                                <option value="">Alle Intents</option>
                                @foreach($availableIntents as $intent)
                                    <option value="{{ $intent }}">{{ ucfirst($intent) }}</option>
                                @endforeach
                            </select>
                            <select wire:model.live="keywordBucket" class="text-[12px] border border-gray-200 rounded-md px-2 py-1.5 bg-white text-gray-600">
                                <option value="">Alle Positionen</option>
                                <option value="top3">Top 3</option>
                                <option value="top10">Top 10</option>
                                <option value="striking">Chancen (4–20)</option>
                                <option value="beyond">&gt; 20 / ungerankt</option>
                            </select>
                            <select wire:model.live="keywordMinVolume" class="text-[12px] border border-gray-200 rounded-md px-2 py-1.5 bg-white text-gray-600">
                                <option value="0">Alle Volumina</option>
                                <option value="100">SV ≥ 100</option>
                                <option value="500">SV ≥ 500</option>
                                <option value="1000">SV ≥ 1.000</option>
                                <option value="5000">SV ≥ 5.000</option>
                            </select>
                            <span class="text-[12px] text-gray-400 tabular-nums">{{ number_format($keywordTotal) }} Keywords</span>
                            @if($keywordIntent !== '' || $keywordBucket !== '' || $keywordMinVolume > 0)
                                <button wire:click="resetKeywordFilters" class="text-[12px] text-gray-400 hover:text-gray-700 inline-flex items-center gap-1">
                                    @svg('heroicon-o-x-mark', 'w-3.5 h-3.5') Filter zurücksetzen
                                </button>
                            @endif
                        </div>
                    @endif

                    @if($keywords->isNotEmpty())
                        <div class="flex gap-0 items-start" style="min-height: 600px;">
                            {{-- Left: Keyword List --}}
                            <div class="flex-1 min-w-0 bg-white rounded-l-lg border border-gray-200 {{ $this->selectedKeyword ? 'border-r-0' : 'rounded-r-lg' }} overflow-hidden flex flex-col">
                                <table class="w-full text-[13px]">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                                            <th class="px-4 py-2.5 text-left">
                                                <button wire:click="sortKeywords('keyword')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700 {{ $keywordSort === 'keyword' ? 'text-gray-900' : '' }}">
                                                    Keyword @if($keywordSort === 'keyword')<span>{{ $keywordSortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                                </button>
                                            </th>
                                            <th class="px-4 py-2.5 text-center w-[70px]">Trend</th>
                                            <th class="px-4 py-2.5 text-right">
                                                <button wire:click="sortKeywords('search_volume')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700 {{ $keywordSort === 'search_volume' ? 'text-gray-900' : '' }}">
                                                    Search @if($keywordSort === 'search_volume')<span>{{ $keywordSortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                                </button>
                                            </th>
                                            <th class="px-4 py-2.5 text-right">
                                                <button wire:click="sortKeywords('cpc')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700 {{ $keywordSort === 'cpc' ? 'text-gray-900' : '' }}">
                                                    CPC @if($keywordSort === 'cpc')<span>{{ $keywordSortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                                </button>
                                            </th>
                                            <th class="px-4 py-2.5 text-right">
                                                <button wire:click="sortKeywords('position')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700 {{ $keywordSort === 'position' ? 'text-gray-900' : '' }}">
                                                    Pos @if($keywordSort === 'position')<span>{{ $keywordSortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                                </button>
                                            </th>
                                            <th class="px-4 py-2.5 text-right w-[52px]">
                                                <button wire:click="sortKeywords('kd')" class="inline-flex items-center gap-1 uppercase tracking-wider hover:text-gray-700 {{ $keywordSort === 'kd' ? 'text-gray-900' : '' }}">
                                                    KD @if($keywordSort === 'kd')<span>{{ $keywordSortDir === 'asc' ? '↑' : '↓' }}</span>@endif
                                                </button>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($keywords as $keyword)
                                            @php
                                                $bestUrl = $keyword->urls->sortBy('pivot.position')->first();
                                                $posChange = ($bestUrl && $bestUrl->pivot->previous_position !== null && $bestUrl->pivot->position !== null)
                                                    ? (int) $bestUrl->pivot->previous_position - (int) $bestUrl->pivot->position
                                                    : null;
                                            @endphp
                                            <tr wire:key="kw-{{ $keyword->id }}"
                                                wire:click="selectKeyword({{ $keyword->id }})"
                                                class="cursor-pointer transition-colors {{ $selectedKeywordId === $keyword->id ? 'bg-blue-50' : 'hover:bg-gray-50' }}">
                                                <td class="px-4 py-2.5">
                                                    <div class="font-medium text-gray-900">{{ $keyword->keyword }}</div>
                                                    @if($keyword->search_intent || ($bestUrl && $bestUrl->id !== $seoUrl->id))
                                                        <div class="flex items-center gap-1.5 mt-0.5">
                                                            @if($keyword->search_intent)
                                                                @php
                                                                    $intentChip = match($keyword->search_intent) {
                                                                        'transactional' => 'bg-green-100 text-green-700',
                                                                        'commercial' => 'bg-blue-100 text-blue-700',
                                                                        'navigational' => 'bg-purple-100 text-purple-700',
                                                                        'informational' => 'bg-gray-100 text-gray-600',
                                                                        default => 'bg-gray-100 text-gray-500',
                                                                    };
                                                                @endphp
                                                                <span class="inline-block px-1.5 py-0.5 rounded text-[9px] font-medium uppercase tracking-wide {{ $intentChip }}">{{ $keyword->search_intent }}</span>
                                                            @endif
                                                            @if($bestUrl && $bestUrl->id !== $seoUrl->id)
                                                                <span class="text-[10px] text-gray-400">{{ ($bestUrl->path && $bestUrl->path !== '/') ? $bestUrl->path : $bestUrl->domain }}</span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-1 py-2.5">
                                                    @if($keyword->monthly_volumes && count($keyword->monthly_volumes) >= 6)
                                                        <div wire:key="trend-{{ $keyword->id }}" wire:ignore
                                                             x-data x-init="$nextTick(() => {
                                                                if (typeof ApexCharts !== 'undefined') {
                                                                    new ApexCharts($el, {
                                                                        chart: { type: 'bar', height: 24, sparkline: { enabled: true } },
                                                                        series: [{ data: {{ json_encode(array_values($keyword->monthly_volumes)) }} }],
                                                                        colors: ['#c7d2fe'],
                                                                        plotOptions: { bar: { borderRadius: 1, columnWidth: '55%' } },
                                                                        tooltip: { enabled: false }
                                                                    }).render();
                                                                }
                                                            })"
                                                             style="height: 24px; width: 56px;">
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular-nums font-medium text-gray-800">
                                                    {{ $keyword->search_volume !== null ? number_format($keyword->search_volume) : '—' }}
                                                </td>
                                                <td class="px-4 py-2.5 text-right tabular-nums text-gray-500 text-[12px]">
                                                    {{ $keyword->cpc_euro !== null ? number_format($keyword->cpc_euro, 2) . '€' : '—' }}
                                                </td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @include('seo::partials.position-badge', ['position' => $bestUrl?->pivot->position, 'change' => $posChange])
                                                </td>
                                                <td class="px-4 py-2.5 text-right">
                                                    @include('seo::partials.kd-badge', ['value' => $keyword->keyword_difficulty])
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                {{-- Load more trigger --}}
                                @if($hasMore)
                                    <div x-data x-intersect="$wire.loadMore()" class="py-4 text-center">
                                        <div wire:loading.delay wire:target="loadMore" class="text-[12px] text-gray-400">Laden...</div>
                                    </div>
                                @endif
                            </div>

                            {{-- Right: Detail Panel --}}
                            @if($this->selectedKeyword)
                                <div class="w-[400px] shrink-0 bg-white rounded-r-lg border border-gray-200 overflow-y-auto sticky top-0" style="max-height: calc(100vh - 120px);">
                                    {{-- Panel Header --}}
                                    <div class="sticky top-0 z-10 bg-white border-b border-gray-100 px-5 py-3 flex items-center justify-between">
                                        <h3 class="text-[13px] font-semibold text-gray-900 truncate">{{ $this->selectedKeyword->keyword }}</h3>
                                        <button wire:click="selectKeyword({{ $this->selectedKeyword->id }})" class="text-gray-400 hover:text-gray-600 p-1">
                                            @svg('heroicon-o-x-mark', 'w-4 h-4')
                                        </button>
                                    </div>
                                    @include('seo::partials.keyword-detail-panel', [
                                        'keyword' => $this->selectedKeyword,
                                        'urls' => $this->selectedKeywordUrls,
                                        'positionHistory' => $this->selectedKeywordHistory,
                                    ])
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">
                            {{ $hasKeywords ? 'Keine Keywords für diese Filter.' : 'Keine Keywords für diese URL.' }}
                        </div>
                    @endif
                @endif

                {{-- Rankings Tab — Positions-Tracker (eine Zeile je Keyword) --}}
                @if($activeTab === 'rankings')
                    @if($rankingSummary && $rankingSummary['total'] > 0)
                        {{-- Summary-Leiste --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Ø Position</div>
                                <div class="text-xl font-bold text-gray-900 tabular-nums">{{ $rankingSummary['avg'] ?? '—' }}</div>
                            </div>
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Keywords</div>
                                <div class="text-xl font-bold text-gray-900 tabular-nums">{{ number_format($rankingSummary['total']) }}</div>
                            </div>
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Top 3</div>
                                <div class="text-xl font-bold text-green-600 tabular-nums">{{ $rankingSummary['top3'] }}</div>
                            </div>
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Top 10</div>
                                <div class="text-xl font-bold text-emerald-600 tabular-nums">{{ $rankingSummary['top10'] }}</div>
                            </div>
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Top 20</div>
                                <div class="text-xl font-bold text-amber-500 tabular-nums">{{ $rankingSummary['top20'] }}</div>
                            </div>
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Trend</div>
                                <div class="flex items-center gap-2 text-sm font-bold tabular-nums">
                                    <span class="text-green-600">▲{{ $rankingSummary['improved'] }}</span>
                                    <span class="text-red-600">▼{{ $rankingSummary['declined'] }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- Tracker-Tabelle --}}
                        <section class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <table class="w-full text-[13px]">
                                <thead>
                                    <tr class="bg-gray-50 border-b border-gray-200 text-[11px] text-gray-500 uppercase tracking-wider">
                                        <th class="px-4 py-2.5 text-left">Keyword</th>
                                        <th class="px-4 py-2.5 text-left">URL</th>
                                        <th class="px-4 py-2.5 text-right">Position</th>
                                        <th class="px-4 py-2.5 text-center w-[120px]">Trend</th>
                                        <th class="px-4 py-2.5 text-left">SERP</th>
                                        <th class="px-4 py-2.5 text-right">Stand</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach($rankingRows as $row)
                                        <tr wire:key="rank-{{ $row['keyword']?->id }}" class="hover:bg-blue-50/50 transition-colors">
                                            <td class="px-4 py-2.5 font-medium text-gray-900">{{ $row['keyword']?->keyword ?? '—' }}</td>
                                            <td class="px-4 py-2.5 text-[11px] text-gray-400">
                                                @if($row['url'] && $row['url']->id !== $seoUrl->id)
                                                    <a href="{{ route('seo.urls.show', $row['url']) }}" wire:navigate class="text-[#166EE1] hover:underline">{{ ($row['url']->path && $row['url']->path !== '/') ? $row['url']->path : $row['url']->domain }}</a>
                                                @else
                                                    {{ ($seoUrl->path && $seoUrl->path !== '/') ? $seoUrl->path : $seoUrl->domain }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right">
                                                @include('seo::partials.position-badge', ['position' => $row['position'], 'change' => $row['delta']])
                                            </td>
                                            <td class="px-2 py-1.5">
                                                @if($row['points'] > 1)
                                                    <div wire:key="rtrend-{{ $row['keyword']?->id }}" class="mx-auto" style="width:100px; height:28px;">
                                                        @include('seo::partials.sparkline', [
                                                            'data' => array_map(fn ($p) => max(1, 101 - $p), $row['trend']),
                                                            'color' => '#10b981',
                                                            'height' => 28,
                                                            'type' => 'area',
                                                        ])
                                                    </div>
                                                @else
                                                    <div class="text-gray-300 text-[11px] text-center">—</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-[11px] text-gray-400">
                                                @if(!empty($row['serp_features']))
                                                    @foreach((array) $row['serp_features'] as $feature)
                                                        <span class="inline-block px-1.5 py-0.5 bg-gray-100 rounded text-[10px] mr-1">{{ $feature }}</span>
                                                    @endforeach
                                                @else
                                                    <span class="text-gray-300">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 text-right text-[11px] text-gray-400">{{ $row['tracked_at']?->format('d.m.Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                        @if($hasMore)
                            <div x-data x-intersect="$wire.loadMore()" class="py-4 text-center">
                                <div wire:loading.delay wire:target="loadMore" class="text-[12px] text-gray-400">Laden...</div>
                            </div>
                        @endif
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">Noch keine Ranking-Historie.</div>
                    @endif
                @endif

                {{-- Backlinks Tab --}}
                @if($activeTab === 'backlinks')
                    {{-- Autoritäts-Summary (Domain-Level aus dem Backlinks-Summary-Call) --}}
                    <div class="grid grid-cols-4 gap-3 mb-6">
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Referring Domains</div>
                            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $seoUrl->referring_domains ?? '—' }}</div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Backlink-Rank</div>
                            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $seoUrl->backlink_rank ?? '—' }}<span class="text-[12px] text-gray-400 font-normal"> / 1000</span></div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Spam-Score</div>
                            <div class="text-2xl font-bold tabular-nums {{ ($seoUrl->backlink_spam_score ?? 0) >= 30 ? 'text-amber-600' : 'text-gray-900' }}">{{ $seoUrl->backlink_spam_score ?? '—' }}<span class="text-[12px] text-gray-400 font-normal"> / 100</span></div>
                        </div>
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Broken</div>
                            <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $seoUrl->broken_backlinks ?? '—' }}</div>
                        </div>
                    </div>
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
                        @if($hasMore)
                            <div x-data x-intersect="$wire.loadMore()" class="py-4 text-center">
                                <div wire:loading.delay wire:target="loadMore" class="text-[12px] text-gray-400">Laden...</div>
                            </div>
                        @endif
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">Keine Backlinks gefunden.</div>
                    @endif
                @endif

                {{-- On-Page Tab --}}
                @if($activeTab === 'onpage')
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
                @endif

                {{-- GSC Tab — echte Google-Sichtbarkeit: Kennzahlen, Discovery, CTR-Chancen --}}
                @if($activeTab === 'gsc')
                    {{-- Aktivierung: an/aus + explizite Property (symmetrisch zu Plausible) --}}
                    <div class="mb-6 bg-white rounded-lg border border-gray-200 p-4">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <div class="text-[13px] font-medium text-gray-900">Google Search Console für <span class="font-semibold">{{ $seoUrl->domain }}</span></div>
                                <div class="text-[12px] text-gray-500 mt-0.5">
                                    @if($seoUrl->gsc_enabled)
                                        Aktiv — wird im GSC-Takt gesammelt.
                                    @else
                                        Inaktiv — aktivieren, wenn diese Domain in GSC verifiziert ist.
                                    @endif
                                </div>
                            </div>
                            <button wire:click="toggleGsc"
                                    class="px-3.5 py-2 text-[13px] font-medium rounded-md transition-colors {{ $seoUrl->gsc_enabled ? 'bg-[#166EE1] text-white hover:bg-[#1259bd]' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                {{ $seoUrl->gsc_enabled ? 'Aktiv' : 'Inaktiv' }}
                            </button>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <label class="block text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Property (optional)</label>
                            <div class="flex items-center gap-2 flex-wrap">
                                <input type="text" wire:model="gscProperty" placeholder="z. B. sc-domain:broichcatering.com oder https://broich.catering/"
                                       class="flex-1 min-w-[240px] text-[13px] border border-gray-300 rounded-md px-3 py-2" />
                                <button wire:click="saveGscProperty" class="text-[13px] font-medium px-3 py-2 rounded-md bg-gray-900 text-white hover:bg-gray-700">Speichern</button>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-1.5">Leer = automatische Domain-Zuordnung. Setzen, wenn die verifizierte Property anders heißt (Alias, z. B. broich.catering ↔ broichcatering.com).</p>
                        </div>
                    </div>

                    @if($seoUrl->gsc_fetched_at)
                        <div class="space-y-6">
                            {{-- Skalar-Kennzahlen (28 Tage, echte Google-Zahlen) --}}
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Klicks (28T)</div>
                                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($seoUrl->gsc_clicks_28d) }}</div>
                                </div>
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Impressionen (28T)</div>
                                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($seoUrl->gsc_impressions_28d) }}</div>
                                </div>
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Ø CTR</div>
                                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $seoUrl->gsc_ctr_28d !== null ? number_format($seoUrl->gsc_ctr_28d * 100, 1) . '%' : '—' }}</div>
                                </div>
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Ø Position</div>
                                    <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $seoUrl->gsc_avg_position !== null ? number_format($seoUrl->gsc_avg_position, 1) : '—' }}</div>
                                </div>
                            </div>

                            {{-- Sichtbarkeits-Verlauf --}}
                            @if(count($gscTrend ?? []) >= 2)
                                <div class="bg-white rounded-lg border border-gray-200 p-3">
                                    <div class="text-[11px] text-gray-500 mb-1">Klick-Verlauf <span class="text-gray-400">(28-Tage-Wert je Messung)</span></div>
                                    @include('seo::partials.sparkline', ['data' => array_column($gscTrend, 'clicks'), 'color' => '#166EE1', 'height' => 50, 'type' => 'area'])
                                    <div class="text-[11px] text-gray-400 mt-1">{{ count($gscTrend) }} Messpunkte seit {{ \Illuminate\Support\Carbon::parse($gscTrend[0]['date'])->format('d.m.Y') }}</div>
                                </div>
                            @endif

                            {{-- Query-Discovery — Ranking-Begriffe OHNE getracktes Keyword: die Goldader Richtung Cluster --}}
                            @if(!empty($seoUrl->gsc_discovered_queries))
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <div class="text-[13px] font-medium text-gray-900 mb-0.5">Query-Discovery <span class="text-[11px] font-normal text-gray-400">· {{ count($seoUrl->gsc_discovered_queries) }} Begriffe</span></div>
                                    <div class="text-[12px] text-gray-500 mb-3">Begriffe, für die Google diese Seite zeigt, die wir aber <span class="font-medium">noch nicht als Keyword führen</span>. Rohstoff für neue Keywords &amp; Cluster — die eigentliche Ernte.</div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-[12px]" style="min-width:460px">
                                            <thead>
                                                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                                    <th class="text-left py-1.5 pr-3">Begriff</th>
                                                    <th class="text-right py-1.5 px-2">Impr.</th>
                                                    <th class="text-right py-1.5 px-2">Klicks</th>
                                                    <th class="text-right py-1.5 pl-2">Ø Pos.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($seoUrl->gsc_discovered_queries as $q)
                                                    <tr class="border-b border-gray-50 last:border-0">
                                                        <td class="py-1.5 pr-3 text-gray-700" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $q['query'] }}">{{ $q['query'] }}</td>
                                                        <td class="py-1.5 px-2 text-right text-gray-600 tabular-nums">{{ number_format($q['impressions']) }}</td>
                                                        <td class="py-1.5 px-2 text-right text-gray-600 tabular-nums">{{ number_format($q['clicks']) }}</td>
                                                        <td class="py-1.5 pl-2 text-right tabular-nums font-medium" style="color:{{ $q['position'] <= 10 ? '#15803d' : ($q['position'] <= 20 ? '#b45309' : '#9ca3af') }}">{{ number_format($q['position'], 1) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            {{-- CTR-Chancen — Seite 1, aber schwache CTR: Title/Snippet-Hebel --}}
                            @if(!empty($seoUrl->gsc_ctr_opportunities))
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <div class="text-[13px] font-medium text-gray-900 mb-0.5">CTR-Chancen <span class="text-[11px] font-normal text-gray-400">· {{ count($seoUrl->gsc_ctr_opportunities) }}</span></div>
                                    <div class="text-[12px] text-gray-500 mb-3">Begriffe auf <span class="font-medium">Seite 1</span> mit schwacher Klickrate — die Position steht, nur das Snippet klickt nicht. Billigster Hebel: Title &amp; Meta-Description schärfen.</div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-[12px]" style="min-width:460px">
                                            <thead>
                                                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                                    <th class="text-left py-1.5 pr-3">Begriff</th>
                                                    <th class="text-right py-1.5 px-2">Ø Pos.</th>
                                                    <th class="text-right py-1.5 px-2">Impr.</th>
                                                    <th class="text-right py-1.5 pl-2">CTR</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($seoUrl->gsc_ctr_opportunities as $q)
                                                    <tr class="border-b border-gray-50 last:border-0">
                                                        <td class="py-1.5 pr-3 text-gray-700" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $q['query'] }}">{{ $q['query'] }}</td>
                                                        <td class="py-1.5 px-2 text-right tabular-nums font-medium text-gray-700">{{ number_format($q['position'], 1) }}</td>
                                                        <td class="py-1.5 px-2 text-right text-gray-600 tabular-nums">{{ number_format($q['impressions']) }}</td>
                                                        <td class="py-1.5 pl-2 text-right tabular-nums font-semibold" style="color:#b91c1c">{{ number_format($q['ctr'] * 100, 1) }}%</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            {{-- Top-Queries — wofür diese Seite tatsächlich rankt (getrackt markiert) --}}
                            @if(!empty($seoUrl->gsc_top_queries))
                                <div class="bg-white rounded-lg border border-gray-200 p-4">
                                    <div class="text-[13px] font-medium text-gray-900 mb-0.5">Top-Begriffe dieser Seite</div>
                                    <div class="text-[12px] text-gray-500 mb-3">Wonach Google diese Seite am meisten zeigt (28 Tage). <span class="inline-block px-1 py-px rounded bg-green-100 text-green-700 text-[10px]">getrackt</span> = bereits als Keyword geführt.</div>
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-[12px]" style="min-width:480px">
                                            <thead>
                                                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                                    <th class="text-left py-1.5 pr-3">Begriff</th>
                                                    <th class="text-right py-1.5 px-2">Impr.</th>
                                                    <th class="text-right py-1.5 px-2">Klicks</th>
                                                    <th class="text-right py-1.5 px-2">CTR</th>
                                                    <th class="text-right py-1.5 pl-2">Ø Pos.</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($seoUrl->gsc_top_queries as $q)
                                                    <tr class="border-b border-gray-50 last:border-0">
                                                        <td class="py-1.5 pr-3 text-gray-700" style="max-width:280px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $q['query'] }}">
                                                            {{ $q['query'] }}
                                                            @if($q['tracked'] ?? false)<span class="ml-1 inline-block px-1 py-px rounded bg-green-100 text-green-700 text-[10px] align-middle">getrackt</span>@endif
                                                        </td>
                                                        <td class="py-1.5 px-2 text-right text-gray-600 tabular-nums">{{ number_format($q['impressions']) }}</td>
                                                        <td class="py-1.5 px-2 text-right text-gray-600 tabular-nums">{{ number_format($q['clicks']) }}</td>
                                                        <td class="py-1.5 px-2 text-right text-gray-500 tabular-nums">{{ number_format($q['ctr'] * 100, 1) }}%</td>
                                                        <td class="py-1.5 pl-2 text-right tabular-nums font-medium" style="color:{{ $q['position'] <= 10 ? '#15803d' : ($q['position'] <= 20 ? '#b45309' : '#9ca3af') }}">{{ number_format($q['position'], 1) }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endif

                            {{-- Pfad-Ebene: GSC-Klicks pro URL (Parent + Kind-Pfade) --}}
                            @if($childUrls->isNotEmpty())
                                <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                                    <table class="w-full text-[13px]">
                                        <thead class="bg-gray-50 text-gray-500">
                                            <tr>
                                                <th class="px-4 py-2.5 text-left font-medium">Pfad</th>
                                                <th class="px-4 py-2.5 text-right font-medium">Klicks (28T)</th>
                                                <th class="px-4 py-2.5 text-right font-medium">Impr. (28T)</th>
                                                <th class="px-4 py-2.5 text-right font-medium">Ø Pos.</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <tr class="bg-gray-50/40">
                                                <td class="px-4 py-2.5 font-medium text-gray-900">{{ $seoUrl->path ?: '/' }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($seoUrl->gsc_clicks_28d) }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($seoUrl->gsc_impressions_28d) }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">{{ $seoUrl->gsc_avg_position !== null ? number_format($seoUrl->gsc_avg_position, 1) : '—' }}</td>
                                            </tr>
                                            @foreach($childUrls->sortByDesc('gsc_clicks_28d') as $child)
                                                <tr>
                                                    <td class="px-4 py-2.5 text-gray-700">{{ $child->path ?: '/' }}</td>
                                                    <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($child->gsc_clicks_28d) }}</td>
                                                    <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($child->gsc_impressions_28d) }}</td>
                                                    <td class="px-4 py-2.5 text-right tabular-nums">{{ $child->gsc_avg_position !== null ? number_format($child->gsc_avg_position, 1) : '—' }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <div class="px-4 py-2 text-[11px] text-gray-400 border-t border-gray-100">Zuletzt aktualisiert: {{ $seoUrl->gsc_fetched_at->format('d.m.Y H:i') }} · Quelle Google Search Console</div>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="p-8 text-center text-[13px] text-gray-400">
                            Noch keine GSC-Daten. Der nächtliche Collector holt sie, sobald eine verifizierte Search-Console-Property für <span class="font-medium">{{ $seoUrl->domain }}</span> gematcht wird.
                        </div>
                    @endif
                @endif

                {{-- Relationships Tab --}}
                @if($activeTab === 'relationships')
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
                @endif

                {{-- Plausible Tab — manuelles Opt-in + Traffic pro Pfad (rollt auf Parent) --}}
                @if($activeTab === 'plausible')
                    <div class="space-y-6">
                        {{-- Opt-in-Schalter: wir wissen, welche Domains in Plausible liegen --}}
                        <div class="flex items-center justify-between bg-white rounded-lg border border-gray-200 p-4">
                            <div>
                                <div class="text-[13px] font-medium text-gray-900">Plausible für <span class="font-semibold">{{ $seoUrl->domain }}</span></div>
                                <div class="text-[12px] text-gray-500 mt-0.5">
                                    @if($seoUrl->plausible_enabled)
                                        Aktiv — der tägliche Collector holt Traffic für diese Domain.
                                    @else
                                        Inaktiv — anhaken, wenn diese Domain in Plausible getrackt wird.
                                    @endif
                                </div>
                            </div>
                            <button wire:click="togglePlausible"
                                    class="px-3.5 py-2 text-[13px] font-medium rounded-md transition-colors {{ $seoUrl->plausible_enabled ? 'bg-[#166EE1] text-white hover:bg-[#1259bd]' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                {{ $seoUrl->plausible_enabled ? 'Aktiviert ✓' : 'Aktivieren' }}
                            </button>
                        </div>

                        {{-- site_id: der echte Plausible-Site-Name (falls ≠ Domain). Leer = Domain als Fallback. --}}
                        <div class="bg-white rounded-lg border border-gray-200 p-4">
                            <div class="text-[13px] font-medium text-gray-900 mb-0.5">Plausible site_id</div>
                            <div class="text-[12px] text-gray-500 mb-2.5">Wie die Site in Plausible <span class="font-medium">wirklich</span> heißt. Leer = die Domain „{{ preg_replace('/^www\./', '', strtolower($seoUrl->domain)) }}" wird verwendet. Bei 401 „Invalid site ID" hier den echten Namen eintragen.</div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <input type="text" wire:model="plausibleSiteId" placeholder="{{ preg_replace('/^www\./', '', strtolower($seoUrl->domain)) }}"
                                       class="text-[13px] border border-gray-200 rounded-md px-3 py-1.5 min-w-[220px] flex-1">
                                <button wire:click="savePlausibleSiteId" class="text-[13px] font-medium px-3 py-1.5 rounded-md bg-gray-900 text-white hover:bg-gray-700">Speichern</button>
                                <button wire:click="testPlausible" wire:loading.attr="disabled" wire:target="testPlausible"
                                        class="text-[13px] font-medium px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-50">
                                    <span wire:loading.remove wire:target="testPlausible">Testen</span>
                                    <span wire:loading wire:target="testPlausible">Teste…</span>
                                </button>
                            </div>
                            @if($plausibleTest)
                                <div class="mt-2.5 text-[12px] px-3 py-2 rounded-md" style="{{ $plausibleTest['ok'] ? 'background:#f0fdf4;color:#15803d' : 'background:#fef2f2;color:#b91c1c' }}">
                                    {{ $plausibleTest['ok'] ? '✓ ' : '✗ ' }}{{ $plausibleTest['msg'] }}
                                </div>
                            @endif
                        </div>

                        {{-- Conversion-Verlauf über Zeit --}}
                        @if(count($conversionTrend ?? []) >= 2)
                            <div class="bg-white rounded-lg border border-gray-200 p-3">
                                <div class="text-[11px] text-gray-500 mb-1">Conversion-Verlauf <span class="text-gray-400">(30-Tage-Wert je Messung)</span></div>
                                @include('seo::partials.sparkline', ['data' => array_column($conversionTrend, 'value'), 'color' => '#0f766e', 'height' => 50, 'type' => 'area'])
                                <div class="text-[11px] text-gray-400 mt-1">{{ count($conversionTrend) }} Messpunkte seit {{ \Illuminate\Support\Carbon::parse($conversionTrend[0]['date'])->format('d.m.Y') }}</div>
                            </div>
                        @endif

                        {{-- Conversion-Attribution je Landingpage — der SEO→Wert-Hebel --}}
                        @if($seoUrl->conversion_pages)
                            @php
                                $cpGroups = collect($seoUrl->conversion_pages);
                                $activeGoal = $conversionGoal && $cpGroups->firstWhere('goal', $conversionGoal) ? $conversionGoal : ($cpGroups->first()['goal'] ?? null);
                                $active = $cpGroups->firstWhere('goal', $activeGoal) ?? $cpGroups->first();
                                $cpPages = collect($active['pages'] ?? [])->sortByDesc('visitors')->values();
                                $cpBest = $cpPages->sortByDesc('rate')->first();
                                $cpLeak = $cpPages->filter(fn ($p) => $p['rate'] < ($active['rate'] ?? 0))->sortByDesc('visitors')->first();
                                $cpMaxRate = max(1, (float) $cpPages->max('rate'));
                            @endphp
                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                <div class="text-[13px] font-medium text-gray-900 mb-0.5">Conversions je Seite</div>
                                <div class="text-[12px] text-gray-500 mb-3">Welche Landingpage bringt dieses Ziel (alle Quellen, 30 Tage) — der Wert-Hebel. Ziel wählen:</div>

                                {{-- Ziel-Switcher --}}
                                <div class="flex items-center gap-1.5 flex-wrap mb-3">
                                    @foreach($cpGroups as $g)
                                        <button wire:click="$set('conversionGoal', @js($g['goal']))"
                                                class="text-[12px] px-2.5 py-1 rounded-full border {{ $g['goal'] === $activeGoal ? 'border-transparent text-white' : 'border-gray-200 text-gray-600 hover:bg-gray-50' }}"
                                                style="{{ $g['goal'] === $activeGoal ? 'background:#0f766e' : '' }}">
                                            {{ $g['goal'] }} <span class="{{ $g['goal'] === $activeGoal ? 'opacity-70' : 'text-gray-400' }} tabular-nums">{{ number_format($g['visitors']) }}</span>
                                        </button>
                                    @endforeach
                                </div>

                                {{-- Insight-Zeile --}}
                                <div class="rounded-md px-3 py-2 mb-3 text-[12px]" style="background:#f0fdfa;color:#0f766e">
                                    Ø <span class="font-semibold tabular-nums">{{ number_format($active['rate'], 1) }}%</span> Conversion-Rate.
                                    @if($cpBest) Stärkste Seite <span class="font-semibold">{{ $cpBest['page'] }}</span> ({{ number_format($cpBest['rate'], 1) }}%) — ausbauen.@endif
                                    @if($cpLeak && ($cpLeak['page'] ?? '') !== ($cpBest['page'] ?? '')) Größtes Leck <span class="font-semibold">{{ $cpLeak['page'] }}</span> ({{ number_format($cpLeak['rate'], 1) }}% bei {{ number_format($cpLeak['visitors']) }} Conv.) — optimieren.@endif
                                </div>

                                {{-- Tabelle: eine je gewähltem Ziel, beschriftet, mit Rate-Balken --}}
                                <div class="overflow-x-auto">
                                    <table class="w-full text-[12px]" style="min-width:440px">
                                        <thead>
                                            <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                                <th class="text-left py-1.5 pr-3">Landingpage</th>
                                                <th class="text-right py-1.5 px-2">Conversions</th>
                                                <th class="text-left py-1.5 pl-2" style="width:150px">Conversion-Rate</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($cpPages as $p)
                                                <tr class="border-b border-gray-50 last:border-0">
                                                    <td class="py-1.5 pr-3 text-gray-700" style="max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $p['page'] }}">{{ $p['page'] }}</td>
                                                    <td class="py-1.5 px-2 text-right text-gray-600 tabular-nums font-medium">{{ number_format($p['visitors']) }}</td>
                                                    <td class="py-1.5 pl-2">
                                                        <div class="flex items-center gap-2">
                                                            <div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden">
                                                                <div class="h-full rounded-full" style="width:{{ max(3, (int) round($p['rate'] / $cpMaxRate * 100)) }}%;background:{{ $p['rate'] >= 20 ? '#15803d' : ($p['rate'] >= 5 ? '#b45309' : '#9ca3af') }}"></div>
                                                            </div>
                                                            <span class="tabular-nums font-semibold w-11 text-right" style="color:{{ $p['rate'] >= 20 ? '#15803d' : ($p['rate'] >= 5 ? '#b45309' : '#6b7280') }}">{{ number_format($p['rate'], 1) }}%</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Organische Landingpages + Engagement — hält die SEO-Tür den Traffic? --}}
                        @if($seoUrl->organic_landing_pages)
                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                <div class="text-[13px] font-medium text-gray-900 mb-0.5">Organische Landingpages</div>
                                <div class="text-[12px] text-gray-500 mb-3">Wo organische Besucher einsteigen — und ob die Seite sie hält (Verweildauer hoch, Bounce niedrig = gut). Das Bindeglied Ranking → Conversion (30 Tage).</div>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-[12px]" style="min-width:460px">
                                        <thead>
                                            <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                                                <th class="text-left py-1.5 pr-3">Einstiegsseite</th>
                                                <th class="text-right py-1.5 px-2">Org. Besucher</th>
                                                <th class="text-right py-1.5 px-2">Verweildauer</th>
                                                <th class="text-right py-1.5 pl-2">Bounce</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($seoUrl->organic_landing_pages as $p)
                                                <tr class="border-b border-gray-50 last:border-0">
                                                    <td class="py-1.5 pr-3 text-gray-700" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="{{ $p['page'] }}">{{ $p['page'] }}</td>
                                                    <td class="py-1.5 px-2 text-right text-gray-600 tabular-nums font-medium">{{ number_format($p['visitors']) }}</td>
                                                    <td class="py-1.5 px-2 text-right tabular-nums" style="color:{{ $p['duration'] >= 120 ? '#15803d' : ($p['duration'] >= 45 ? '#b45309' : '#6b7280') }}">{{ $p['duration'] >= 60 ? intdiv($p['duration'], 60) . 'm ' . ($p['duration'] % 60) . 's' : $p['duration'] . 's' }}</td>
                                                    <td class="py-1.5 pl-2 text-right tabular-nums font-semibold" style="color:{{ $p['bounce'] <= 40 ? '#15803d' : ($p['bounce'] <= 65 ? '#b45309' : '#b91c1c') }}">{{ $p['bounce'] }}%</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        {{-- Roll-up: Domain-Total (Parent + Kinder) --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Visitors (30T)</div>
                                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $aggVisitors === null ? '—' : number_format($aggVisitors) }}</div>
                            </div>
                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">Pageviews (30T)</div>
                                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ number_format($aggPageviews) }}</div>
                            </div>
                        </div>

                        {{-- Pfad-Ebene: Traffic pro URL (Parent + Kind-Pfade) --}}
                        <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                            <table class="w-full text-[13px]">
                                <thead class="bg-gray-50 text-gray-500">
                                    <tr>
                                        <th class="px-4 py-2.5 text-left font-medium">Pfad</th>
                                        <th class="px-4 py-2.5 text-right font-medium">Visitors (30T)</th>
                                        <th class="px-4 py-2.5 text-right font-medium">Pageviews (30T)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr class="bg-gray-50/40">
                                        <td class="px-4 py-2.5 font-medium text-gray-900">{{ $seoUrl->path ?: '/' }}</td>
                                        <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($seoUrl->visitors_30d) }}</td>
                                        <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($seoUrl->pageviews_30d) }}</td>
                                    </tr>
                                    @foreach($childUrls->sortByDesc('visitors_30d') as $child)
                                        <tr>
                                            <td class="px-4 py-2.5 text-gray-700">{{ $child->path ?: '/' }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($child->visitors_30d) }}</td>
                                            <td class="px-4 py-2.5 text-right tabular-nums">{{ number_format($child->pageviews_30d) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if($seoUrl->traffic_fetched_at)
                                <div class="px-4 py-2 text-[11px] text-gray-400 border-t border-gray-100">Zuletzt aktualisiert: {{ $seoUrl->traffic_fetched_at->format('d.m.Y H:i') }} · Quelle Plausible</div>
                            @endif
                        </div>
                    </div>
                @endif

                {{-- AI-Sichtbarkeit Tab — LLM Mentions (ChatGPT + Google AI Overview) --}}
                @if($activeTab === 'ai')
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">LLM Mentions</div>
                                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $seoUrl->llm_mentions ?? '—' }}</div>
                                <div class="text-[11px] text-gray-400 mt-1">Erwähnungen in AI-Antworten</div>
                            </div>
                            <div class="bg-white rounded-lg border border-gray-200 p-4">
                                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mb-1">AI Search Volume</div>
                                <div class="text-2xl font-bold text-gray-900 tabular-nums">{{ $seoUrl->llm_ai_search_volume !== null ? number_format($seoUrl->llm_ai_search_volume) : '—' }}</div>
                                <div class="text-[11px] text-gray-400 mt-1">Suchvolumen im AI-Kontext</div>
                            </div>
                        </div>

                        @if(!empty($seoUrl->llm_mentions_data['platform']) && is_array($seoUrl->llm_mentions_data['platform']))
                            <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
                                <table class="w-full text-[13px]">
                                    <thead class="bg-gray-50 text-gray-500">
                                        <tr>
                                            <th class="px-4 py-2.5 text-left font-medium">Plattform</th>
                                            <th class="px-4 py-2.5 text-right font-medium">Mentions</th>
                                            <th class="px-4 py-2.5 text-right font-medium">AI Search Volume</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @foreach($seoUrl->llm_mentions_data['platform'] as $row)
                                            <tr>
                                                <td class="px-4 py-2.5 text-gray-700">{{ $row['type'] ?? $row['platform'] ?? '—' }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">{{ isset($row['mentions']) ? number_format($row['mentions']) : '—' }}</td>
                                                <td class="px-4 py-2.5 text-right tabular-nums">{{ isset($row['ai_search_volume']) ? number_format($row['ai_search_volume']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        @if($seoUrl->llm_mentions_fetched_at)
                            <div class="text-[11px] text-gray-400">Zuletzt aktualisiert: {{ $seoUrl->llm_mentions_fetched_at->format('d.m.Y H:i') }} · Quelle DataForSEO LLM Mentions</div>
                        @else
                            <div class="text-[13px] text-gray-400">Noch keine AI-Sichtbarkeitsdaten — Collector „llm_mentions" ausführen.</div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </x-ui-page-container>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
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
