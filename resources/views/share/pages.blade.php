{{-- Kunden-Tor · Sichtbarkeit / Seiten: Besucher, Google-Klicks und Keywords je Seite. Daten: $d. --}}
@php
    $n = fn ($v) => $v === null ? '–' : number_format((float) $v, 0, ',', '.');
@endphp
<div class="gp-wrap py-6">
    <div class="gp-card overflow-hidden">
        <div class="hidden grid-cols-[1fr_110px_110px_90px_90px] gap-3 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)] md:grid">
            <span>Seite</span><span class="text-right">Besucher 30 T.</span><span class="text-right">Klicks 28 T.</span><span class="text-right">Keywords</span><span class="text-right">Ø Pos.</span>
        </div>
        <div class="divide-y divide-[color:var(--nx-line)]">
            @foreach ($d['pages'] as $p)
                <div class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-0.5 px-4 py-2.5 text-sm md:grid-cols-[1fr_110px_110px_90px_90px]">
                    <a href="{{ $p['url'] }}" target="_blank" rel="noopener" class="min-w-0 truncate hover:underline">
                        <span class="text-[color:var(--nx-faint)]">{{ $p['domain'] }}</span>{{ $p['path'] === '/' ? '' : $p['path'] }}
                    </a>
                    <span class="text-right tabular-nums">{{ $n($p['visitors']) }}<span class="text-xs text-[color:var(--nx-faint)] md:hidden"> Besucher</span></span>
                    <span class="hidden text-right tabular-nums text-[color:var(--nx-muted)] md:block">{{ $n($p['clicks']) }}</span>
                    <span class="hidden text-right tabular-nums text-[color:var(--nx-muted)] md:block">{{ $n($p['keywords']) }}</span>
                    <span class="hidden text-right tabular-nums text-[color:var(--nx-muted)] md:block">{{ $p['position'] ? number_format($p['position'], 1, ',', '.') : '–' }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
