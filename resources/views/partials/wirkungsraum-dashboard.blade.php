{{-- Wirkungsraum-Dashboard — die gecraftete Überblicks-Sicht (nx-Sprache).
     Erwartet: $agg, $health, $trend, $penetration, $competitors. --}}

{{-- Posteingang — die Zentrale: vorgeschlagene Maßnahmen zuerst --}}
@if(($measureInbox['proposed'] ?? 0) > 0 || ($measureInbox['accepted'] ?? 0) > 0)
    <div class="mb-4">
        <x-nx-card>
            <div class="flex items-center justify-between gap-4 flex-wrap">
                <div class="min-w-0">
                    <div class="flex items-baseline gap-2">
                        <span class="text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Posteingang</span>
                        @if(($measureInbox['proposed'] ?? 0) > 0)
                            <span class="text-[22px] font-semibold tabular-nums text-[color:var(--nx-text)]">{{ number_format($measureInbox['proposed']) }}</span>
                            <span class="text-[12px] text-[color:var(--nx-muted)]">neu</span>
                        @else
                            <span class="text-[13px] font-medium text-[color:var(--nx-muted)]">nichts Neues</span>
                        @endif
                        @if(($measureInbox['accepted'] ?? 0) > 0)
                            <span class="text-[11px] text-[color:var(--nx-faint)]">· {{ number_format($measureInbox['accepted']) }} in Queue</span>
                        @endif
                    </div>
                    @if(! empty($measureInbox['top']))
                        <div class="mt-1 text-[13px] text-[color:var(--nx-text)] truncate">Top: {{ $measureInbox['top']->title }}</div>
                    @endif
                </div>
                <button type="button" wire:click="setView('vertiefen')"
                        class="shrink-0 inline-flex items-center gap-1.5 text-[12px] font-medium px-3 py-1.5 rounded-md bg-[color:var(--nx-text)] text-[color:var(--nx-bg)] hover:opacity-90">
                    Zum Posteingang <span aria-hidden="true">→</span>
                </button>
            </div>
        </x-nx-card>
    </div>
@endif

{{-- Hero: Nordstern-Metriken + der eine nächste Zug --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

    {{-- Sichtbarkeit (Held) + Verlauf --}}
    <x-nx-card>
        <div class="text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Sichtbarkeit</div>
        <div class="mt-1 flex items-baseline gap-2">
            <span class="text-[34px] leading-none font-semibold tabular-nums text-[color:var(--nx-text)]">{{ number_format($agg['visibility'], 0) }}</span>
            @if(($trend['delta'] ?? null) !== null && $trend['delta'] != 0)
                <span class="text-[12px] tabular-nums {{ $trend['delta'] > 0 ? 'text-[color:var(--nx-success)]' : 'text-[color:var(--nx-danger)]' }}">{{ $trend['delta'] > 0 ? '▲ +' : '▼ ' }}{{ number_format($trend['delta'], 0) }}</span>
            @endif
        </div>
        @if(! empty($trend['spark']))
            <svg viewBox="0 0 {{ $trend['spark']['w'] }} {{ $trend['spark']['h'] }}" preserveAspectRatio="none" class="mt-3 w-full" style="height:40px">
                <polygon points="{{ $trend['spark']['area'] }}" fill="color-mix(in srgb, var(--nx-info) 12%, transparent)" />
                <polyline points="{{ $trend['spark']['line'] }}" fill="none" stroke="var(--nx-info)" stroke-width="1.5" vector-effect="non-scaling-stroke" />
            </svg>
            <div class="mt-1 text-[10px] text-[color:var(--nx-faint)]">Verbund-Verlauf · {{ $trend['count'] }} Messpunkte</div>
        @else
            <div class="mt-3 text-[11px] text-[color:var(--nx-faint)]">Noch kein Verlauf — Snapshots sammeln sich mit den Messungen.</div>
        @endif
    </x-nx-card>

    {{-- Wirkungsgrad (IST ÷ SOLL über die Themen) --}}
    <x-nx-card>
        <div class="text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Wirkungsgrad</div>
        <div class="mt-1 flex items-baseline gap-1">
            <span class="text-[34px] leading-none font-semibold tabular-nums text-[color:var(--nx-text)]">{{ $health['dimensions']['durchdringung'] ?? 0 }}</span>
            <span class="text-[16px] text-[color:var(--nx-muted)]">%</span>
        </div>
        <div class="mt-3 h-1.5 rounded-full bg-[color:var(--nx-line)] overflow-hidden">
            <div class="h-full rounded-full" style="width: {{ max(2, (int) ($health['dimensions']['durchdringung'] ?? 0)) }}%; background: var(--nx-info)"></div>
        </div>
        <div class="mt-2 text-[11px] text-[color:var(--nx-faint)]">Ø IST/SOLL je Thema · Ordnung {{ $health['dimensions']['ordnung'] ?? 0 }}%</div>
    </x-nx-card>

    {{-- Nächster Zug — die EINE Handlung --}}
    <x-nx-card>
        <div class="text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Nächster Zug · {{ $health['current_label'] }}</div>
        <div class="mt-1 text-[15px] font-medium leading-snug text-[color:var(--nx-text)]">{{ $health['next_action'] }}</div>
        <div class="mt-1 text-[12px] leading-snug text-[color:var(--nx-muted)]">{{ $health['reason'] }}</div>
        <button type="button" wire:click="setView('{{ $health['current'] }}')"
                class="mt-3 inline-flex items-center gap-1.5 text-[12px] font-medium px-3 py-1.5 rounded-md bg-[color:var(--nx-text)] text-[color:var(--nx-bg)] hover:opacity-90 transition-opacity">
            Zur Station „{{ $health['current_label'] }}"
            <span aria-hidden="true">→</span>
        </button>
    </x-nx-card>
</div>

{{-- KPI-Strip --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
    <x-nx-stat label="Keywords" :value="number_format($agg['keywords'])" />
    <x-nx-stat label="Suchvolumen" :value="number_format($agg['search_volume'])" />
    <x-nx-stat label="URLs" :value="number_format($agg['urls'])" />
    <x-nx-stat label="Cluster" :value="number_format($penetration['clusters']->count())" :hint="$competitors->count().' Wettbewerber'" />
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
