{{-- Kunden-Tor · Sichtbarkeit / Überblick: Besucher je Woche, Positionsverteilung, Gewinner & Verlierer, Suchanfragen. Daten: $d (SeoShareView). --}}
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $weeks = $d['traffic']['weeks'];
    $maxW = max(1, collect($weeks)->max('visitors'));
    $dist = $d['positions']['dist'];
    $maxD = max(1, max($dist));
    $posBadge = fn (int $p) => $p <= 3 ? 'success' : ($p <= 10 ? 'info' : 'neutral');
@endphp
<div class="gp-wrap py-6">
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-5">
        {{-- Besucher je Woche --}}
        <div class="gp-card p-4 lg:col-span-3">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-medium">Besucher je Woche</span>
                <span class="text-xs text-[color:var(--nx-faint)]">{{ $d['traffic']['visitors'] !== null ? $n($d['traffic']['visitors']) . ' in 30 Tagen' : 'keine Besucherdaten' }}</span>
            </div>
            @if ($weeks)
                <div class="gp-bars mt-4">
                    @foreach ($weeks as $w)
                        <div title="{{ $w['label'] }} (ab {{ $w['from'] }}): {{ $n($w['visitors']) }} Besucher">
                            @if ($w['visitors'] === $maxW && $w['visitors'] > 0)<span class="tabular-nums" style="color: var(--nx-muted)">{{ $n($w['visitors']) }}</span>@endif
                            <div @class(['gp-bar', 'is-now' => $loop->last]) style="height: {{ $w['visitors'] ? max(4, (int) round($w['visitors'] / $maxW * 72)) : 2 }}px"></div>
                            <span>{{ str_replace('KW ', '', $w['label']) }}</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-4 rounded-[8px] border border-dashed border-[color:var(--nx-line-strong)] px-4 py-8 text-center text-sm text-[color:var(--nx-faint)]">Für diese Website werden (noch) keine Besucher gemessen.</div>
            @endif
        </div>

        {{-- Positionen --}}
        <div class="gp-card p-4 lg:col-span-2">
            <div class="flex items-baseline justify-between">
                <span class="text-sm font-medium">Wo Sie bei Google stehen</span>
                <span class="text-xs text-[color:var(--nx-faint)]">{{ $n($d['positions']['ranking']) }} Keywords</span>
            </div>
            <div class="mt-3 space-y-2">
                @foreach ($dist as $label => $count)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-20 shrink-0 whitespace-nowrap text-xs text-[color:var(--nx-muted)]">Platz {{ $label }}</span>
                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-[color:var(--nx-line)]">
                            <div class="h-full rounded-full" style="width: {{ (int) round($count / $maxD * 100) }}%; background: {{ $loop->index === 0 ? 'var(--nx-success)' : ($loop->index === 1 ? 'var(--nx-info)' : 'color-mix(in srgb, var(--nx-info) 35%, transparent)') }}"></div>
                        </div>
                        <span class="w-10 shrink-0 text-right tabular-nums">{{ $n($count) }}</span>
                    </div>
                @endforeach
            </div>
            @if ($d['llm']['mentions'])
                <div class="mt-4 flex items-center gap-2 border-t border-[color:var(--nx-line)] pt-3 text-sm">
                    @svg('heroicon-o-sparkles', 'w-4 h-4 text-[color:var(--nx-tone-violet)]')
                    <span><span class="font-medium tabular-nums">{{ $n($d['llm']['mentions']) }}</span> Erwähnungen in KI-Antworten</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Gewinner & Verlierer --}}
    <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
        @foreach ([['up', 'Aufgestiegen', 'heroicon-s-arrow-trending-up', 'var(--nx-success)', $d['movers']['improved']], ['down', 'Abgerutscht', 'heroicon-s-arrow-trending-down', 'var(--nx-danger)', $d['movers']['declined']]] as [$key, $label, $icon, $color, $count])
            <div class="gp-card p-4">
                <div class="mb-2 flex items-center gap-2 text-sm font-medium">
                    <span style="color: {{ $color }}">@svg($icon, 'w-4 h-4')</span>{{ $label }}
                    <span class="font-normal text-[color:var(--nx-faint)]">{{ $count }} Keywords seit der letzten Messung</span>
                </div>
                @forelse ($d['movers'][$key] as $k)
                    <div class="flex items-center gap-3 border-t border-[color:var(--nx-line)] py-2 text-sm first:border-t-0">
                        <span class="min-w-0 flex-1 truncate">{{ $k['keyword'] }}</span>
                        <span class="shrink-0 text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $k['volume'] ? $n($k['volume']) . ' / Monat' : '' }}</span>
                        <span class="w-24 shrink-0 text-right text-xs tabular-nums">
                            <span class="text-[color:var(--nx-faint)]">{{ $k['previous'] }} →</span>
                            <x-nx-badge :variant="$posBadge($k['position'])">{{ $k['position'] }}</x-nx-badge>
                        </span>
                    </div>
                @empty
                    <div class="py-4 text-center text-sm text-[color:var(--nx-faint)]">Keine Veränderung.</div>
                @endforelse
            </div>
        @endforeach
    </div>

    {{-- Suchanfragen --}}
    @if ($d['gsc']['queries'])
        <div class="gp-card mt-3 p-4">
            <div class="mb-2 flex items-baseline justify-between">
                <span class="text-sm font-medium">Womit man Sie bei Google findet</span>
                <span class="text-xs text-[color:var(--nx-faint)]">Search Console · 28 Tage</span>
            </div>
            <div class="grid grid-cols-1 gap-x-8 md:grid-cols-2">
                @foreach ($d['gsc']['queries'] as $q)
                    <div class="flex items-center gap-3 border-t border-[color:var(--nx-line)] py-2 text-sm">
                        <span class="min-w-0 flex-1 truncate">{{ $q['query'] }}</span>
                        <span class="shrink-0 whitespace-nowrap text-xs tabular-nums text-[color:var(--nx-muted)]">{{ $n($q['clicks']) }} Klicks</span>
                        <span class="w-36 shrink-0 whitespace-nowrap text-right text-xs tabular-nums text-[color:var(--nx-faint)]">{{ $n($q['impressions']) }} Einblendungen</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
