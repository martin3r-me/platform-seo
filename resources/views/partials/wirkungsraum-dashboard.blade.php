{{-- Wirkungsraum-Dashboard — die gecraftete Ueberblicks-Sicht (nx-Sprache).
     Dreht sich um: bedeutungsvolle Sichtbarkeit (Marktanteil + Verlauf),
     Cluster-Durchdringung und offene Aufgaben (inkl. ungeclustertem Rest).
     Erwartet: $agg, $health, $trend, $penetration, $competitors, $measureInbox.
     Durchgaengig Block-Direktiven. --}}
@php
    // --- Sichtbarkeit bedeutungsvoll machen: Anteil am erfassten Markt ---
    $ownVis = (float) ($agg['visibility'] ?? 0);
    $compVisSum = (float) ($competitors->sum('visibility') ?? 0);
    $marketVis = $ownVis + $compVisSum;
    $share = $marketVis > 0 ? (int) round($ownVis / $marketVis * 100) : null;
    // Rang: wie viele Wettbewerber-Domains sind sichtbarer als wir?
    $ahead = $competitors->filter(fn ($c) => (float) ($c->visibility ?? 0) > $ownVis)->count();
    $rank = $ahead + 1;
    $fieldSize = $competitors->count() + 1;

    // --- Cluster-Durchdringung ---
    $clusters = $penetration['clusters'] ?? collect();
    $clusterCount = $clusters->count();
    $besetzt = $clusters->filter(fn ($c) => ($c['ist'] ?? 0) > 0)->count();
    $tief = $clusters->filter(fn ($c) => ($c['pct'] ?? 0) >= 70)->count();
    $mittel = $clusters->filter(fn ($c) => ($c['pct'] ?? 0) >= 30 && ($c['pct'] ?? 0) < 70)->count();
    $offen = $clusterCount - $tief - $mittel;
    $topClusters = $clusters->take(5);

    // --- Ungeclusterter Rest (potenzielle Aufgabe, schon vorhanden) ---
    $uncl = $penetration['unclustered'] ?? null;
    $unclKw = (int) ($uncl['soll'] ?? 0);
    $unclVol = (int) ($uncl['volume'] ?? 0);

    $proposed = (int) ($measureInbox['proposed'] ?? 0);
    $accepted = (int) ($measureInbox['accepted'] ?? 0);
    $hasTrend = ! empty($trend['spark']);
    $delta = $trend['delta'] ?? null;
@endphp

{{-- ======================= 1 · SICHTBARKEIT + VERLAUF ======================= --}}
{{-- Der Held: nicht die nackte Indexzahl, sondern ihr Wert im Feld + Richtung. --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
    <x-nx-card class="lg:col-span-2">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Sichtbarkeit im Wirkungsraum</div>
                @if($share !== null)
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-[40px] leading-none font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $share }}<span class="text-[20px] text-[color:var(--nx-muted)]">%</span></span>
                        <span class="text-[12px] font-medium px-1.5 py-0.5 rounded {{ $rank === 1 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-muted)]' }}" style="background:{{ $rank === 1 ? 'color-mix(in srgb, var(--nx-success) 12%, transparent)' : 'var(--nx-line)' }}">#{{ $rank }} von {{ $fieldSize }}</span>
                    </div>
                    <div class="mt-1 text-[12px] text-[color:var(--nx-muted)]">unser Anteil am Sichtbarkeits-Feld — <span class="tabular-nums">eigene {{ number_format($ownVis, 0) }}</span> vs. <span class="tabular-nums">Wettbewerber {{ number_format($compVisSum, 0) }}</span></div>
                @else
                    <div class="mt-1 flex items-baseline gap-2">
                        <span class="text-[40px] leading-none font-semibold tabular-nums text-[color:var(--nx-text)]">{{ number_format($ownVis, 0) }}</span>
                        @if($delta !== null && $delta != 0)
                            <span class="text-[13px] tabular-nums {{ $delta > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">{{ $delta > 0 ? '▲ +' : '▼ ' }}{{ number_format($delta, 0) }}</span>
                        @endif
                    </div>
                    <div class="mt-1 text-[12px] text-[color:var(--nx-faint)]">Index aus Position × Volumen — erst im Verlauf und gegen Wettbewerber aussagekräftig. Noch keine Wettbewerber erfasst.</div>
                @endif
            </div>
            @if($share !== null && $delta !== null && $delta != 0)
                <div class="shrink-0 text-right">
                    <div class="text-[13px] font-semibold tabular-nums {{ $delta > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">{{ $delta > 0 ? '▲ +' : '▼ ' }}{{ number_format($delta, 0) }}</div>
                    <div class="text-[10px] text-[color:var(--nx-faint)]">seit Start</div>
                </div>
            @endif
        </div>

        {{-- Verlauf: die eigentliche Aussage des Index ist seine Richtung --}}
        @if($hasTrend)
            <svg viewBox="0 0 {{ $trend['spark']['w'] }} {{ $trend['spark']['h'] }}" preserveAspectRatio="none" class="mt-4 w-full" style="height:52px">
                <polygon points="{{ $trend['spark']['area'] }}" fill="color-mix(in srgb, var(--nx-info) 12%, transparent)" />
                <polyline points="{{ $trend['spark']['line'] }}" fill="none" stroke="var(--nx-info)" stroke-width="1.5" vector-effect="non-scaling-stroke" />
            </svg>
            <div class="mt-1 text-[10px] text-[color:var(--nx-faint)]">Verbund-Verlauf · {{ $trend['count'] }} Messpunkte — steigt der Anteil, gewinnen wir das Feld.</div>
        @else
            <div class="mt-4 rounded-md border border-dashed border-[color:var(--nx-line)] px-3 py-3 text-[11px] text-[color:var(--nx-faint)]">
                <span class="font-medium text-[color:var(--nx-muted)]">Verlauf entsteht ab dem 2. Messpunkt.</span> Der Wert einer Sichtbarkeit liegt in ihrer Richtung — der Rahmen steht, die Linie kommt mit den Snapshots.
            </div>
        @endif
    </x-nx-card>

    {{-- ===================== 2 · CLUSTER-DURCHDRINGUNG ===================== --}}
    <x-nx-card>
        <div class="flex items-baseline justify-between">
            <div class="text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Durchdringung</div>
            <button wire:click="setView('organize')" class="text-[11px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Ordnen →</button>
        </div>
        @if($clusterCount > 0)
            <div class="mt-1 flex items-baseline gap-1">
                <span class="text-[34px] leading-none font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $besetzt }}</span>
                <span class="text-[15px] text-[color:var(--nx-muted)]">/ {{ $clusterCount }}</span>
                <span class="text-[11px] text-[color:var(--nx-faint)] ml-1">Themen besetzt</span>
            </div>
            {{-- Verteilungs-Balken tief/mittel/offen --}}
            <div class="mt-3 flex h-2 rounded-full overflow-hidden bg-[color:var(--nx-line)]">
                @if($tief > 0)<div style="width:{{ $tief / $clusterCount * 100 }}%; background:var(--nx-success)" title="{{ $tief }} tief besetzt (≥70%)"></div>@endif
                @if($mittel > 0)<div style="width:{{ $mittel / $clusterCount * 100 }}%; background:var(--nx-warning)" title="{{ $mittel }} mittel (30–69%)"></div>@endif
                @if($offen > 0)<div style="width:{{ $offen / $clusterCount * 100 }}%; background:color-mix(in srgb, var(--nx-danger) 45%, transparent)" title="{{ $offen }} offen (<30%)"></div>@endif
            </div>
            <div class="mt-2 flex items-center gap-3 text-[10px] text-[color:var(--nx-faint)]">
                <span><span style="color:var(--nx-success)">●</span> {{ $tief }} tief</span>
                <span><span style="color:var(--nx-warning)">●</span> {{ $mittel }} mittel</span>
                <span><span style="color:color-mix(in srgb, var(--nx-danger) 60%, transparent)">●</span> {{ $offen }} offen</span>
            </div>
            <div class="mt-1 text-[10px] text-[color:var(--nx-faint)]">Ordnung {{ $health['dimensions']['ordnung'] ?? 0 }}% · Ø Tiefe {{ $health['dimensions']['durchdringung'] ?? 0 }}%</div>
        @else
            <div class="mt-2 text-[11px] text-[color:var(--nx-faint)]">Noch keine Cluster — in „Ordnen" die Themen bauen.</div>
        @endif
    </x-nx-card>
</div>

{{-- ===================== 3 · OFFENE AUFGABEN ===================== --}}
{{-- Was ansteht: der nächste Gate-Zug + der ungeclusterte Rest + der Posteingang. --}}
<x-nx-card class="mb-4">
    <div class="text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)] mb-2">Offene Aufgaben</div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        {{-- Nächster Zug (Gate) --}}
        <button wire:click="setView('{{ $health['current'] }}')" class="text-left rounded-md border border-[color:var(--nx-line)] px-3 py-2.5 hover:border-[color:var(--nx-info)] transition-colors">
            <div class="text-[10px] uppercase tracking-wide text-[color:var(--nx-faint)]">Nächster Zug · {{ $health['current_label'] }}</div>
            <div class="mt-0.5 text-[13px] font-medium leading-snug text-[color:var(--nx-text)]">{{ $health['next_action'] }}</div>
            <div class="mt-0.5 text-[11px] leading-snug text-[color:var(--nx-muted)] line-clamp-2">{{ $health['reason'] }}</div>
        </button>

        {{-- Ungeclusterter Rest (schon vorhanden) --}}
        <button wire:click="setView('organize')" class="text-left rounded-md border px-3 py-2.5 transition-colors {{ $unclKw > 0 ? 'border-[color:var(--nx-line)] hover:border-[color:var(--nx-warning)]' : 'border-[color:var(--nx-line)] opacity-70' }}">
            <div class="text-[10px] uppercase tracking-wide text-[color:var(--nx-faint)]">Ungeclusterter Rest</div>
            @if($unclKw > 0)
                <div class="mt-0.5 flex items-baseline gap-1.5">
                    <span class="text-[20px] font-semibold tabular-nums text-[color:var(--nx-text)]">{{ number_format($unclKw) }}</span>
                    <span class="text-[11px] text-[color:var(--nx-muted)]">Keywords ohne Thema</span>
                </div>
                <div class="mt-0.5 text-[11px] text-[color:var(--nx-muted)]">{{ number_format($unclVol) }} Suchvolumen wartet auf ein Cluster → Ordnen</div>
            @else
                <div class="mt-0.5 text-[13px] font-medium text-[color:var(--nx-success)]">✓ alles geclustert</div>
                <div class="mt-0.5 text-[11px] text-[color:var(--nx-faint)]">kein loser Rest</div>
            @endif
        </button>

        {{-- Posteingang --}}
        <button wire:click="setView('act')" class="text-left rounded-md border px-3 py-2.5 transition-colors {{ $proposed > 0 ? 'border-[color:var(--nx-line)] hover:border-[color:var(--nx-info)]' : 'border-[color:var(--nx-line)] opacity-70' }}">
            <div class="text-[10px] uppercase tracking-wide text-[color:var(--nx-faint)]">Posteingang</div>
            @if($proposed > 0)
                <div class="mt-0.5 flex items-baseline gap-1.5">
                    <span class="text-[20px] font-semibold tabular-nums text-[color:var(--nx-text)]">{{ number_format($proposed) }}</span>
                    <span class="text-[11px] text-[color:var(--nx-muted)]">neue Maßnahmen</span>
                </div>
                <div class="mt-0.5 text-[11px] text-[color:var(--nx-muted)] truncate">@if(! empty($measureInbox['top']))Top: {{ $measureInbox['top']->title }}@endif</div>
            @else
                <div class="mt-0.5 text-[13px] font-medium text-[color:var(--nx-muted)]">nichts Neues</div>
                <div class="mt-0.5 text-[11px] text-[color:var(--nx-faint)]">@if($accepted > 0){{ $accepted }} in Queue @else Posteingang leer @endif</div>
            @endif
        </button>
    </div>
</x-nx-card>

{{-- KPI-Strip — die nackten Fakten, bewusst klein (die Deutung steht oben) --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <x-nx-stat label="Keywords" :value="number_format($agg['keywords'])" />
    <x-nx-stat label="Suchvolumen" :value="number_format($agg['search_volume'])" hint="Nachfrage gesamt" />
    <x-nx-stat label="URLs" :value="number_format($agg['urls'])" :hint="$competitors->count().' Wettbewerber'" />
    <x-nx-stat label="Cluster" :value="number_format($clusterCount)" hint="Themen" />
</div>

{{-- Reifegrad — kompakt & passiv (Navigation läuft über die Sidebar) --}}
<x-nx-card>
    <div class="flex items-center justify-between mb-3">
        <span class="text-[13px] font-semibold text-[color:var(--nx-text)]">Reifegrad</span>
        <span class="text-[11px] text-[color:var(--nx-faint)]">Trichter — Phase = erstes offenes Gate</span>
    </div>
    <div class="flex items-stretch gap-1.5">
        @foreach($health['phases'] as $ph)
            <button type="button" wire:click="setView('{{ $ph['key'] }}')" class="flex-1 text-left group">
                <div class="h-1.5 rounded-full" style="background: {{ $ph['status'] === 'done' ? 'var(--nx-success)' : ($ph['status'] === 'current' ? 'var(--nx-info)' : 'var(--nx-line)') }}"></div>
                <div class="mt-1.5 flex items-center gap-1 text-[11px] {{ $ph['status'] === 'current' ? 'font-semibold text-[color:var(--nx-text)]' : 'text-[color:var(--nx-faint)] group-hover:text-[color:var(--nx-muted)]' }}">
                    <span>{{ $ph['status'] === 'done' ? '✓' : ($ph['status'] === 'current' ? '●' : '○') }}</span>
                    <span class="truncate">{{ $ph['label'] }}</span>
                </div>
            </button>
        @endforeach
    </div>
</x-nx-card>
