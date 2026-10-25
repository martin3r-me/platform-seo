{{-- Kunden-Tor · Sichtbarkeit / Keywords: wofür die Website rankt, mit Veränderung und Suchvolumen. Daten: $d. --}}
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', '.');
    $posBadge = fn (int $p) => $p <= 3 ? 'success' : ($p <= 10 ? 'info' : 'neutral');
@endphp
<div class="gp-wrap py-6" x-data="{ q: '', top: false }">
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <label class="flex min-w-0 flex-1 items-center gap-2 rounded-[6px] border border-[color:var(--nx-line)] bg-[color:var(--nx-surface)] px-2.5 py-1.5 sm:max-w-xs">
            @svg('heroicon-o-magnifying-glass', 'w-4 h-4 shrink-0 text-[color:var(--nx-faint)]')
            <input type="search" x-model="q" placeholder="Keyword suchen" class="min-w-0 flex-1 bg-transparent text-sm outline-none">
        </label>
        <label class="inline-flex cursor-pointer items-center gap-2 text-sm text-[color:var(--nx-muted)]">
            <input type="checkbox" x-model="top" class="rounded"> nur Top 10
        </label>
        <span class="ml-auto text-xs text-[color:var(--nx-faint)]">{{ count($d['keywords']) }} Keywords{{ count($d['keywords']) >= 200 ? ' (die besten 200)' : '' }}</span>
    </div>
    <div class="gp-card overflow-hidden">
        <div class="hidden grid-cols-[1fr_110px_90px_180px] gap-3 border-b border-[color:var(--nx-line)] bg-[color:var(--nx-bg)] px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)] md:grid">
            <span>Keyword</span><span class="text-right">Suchvolumen</span><span class="text-right">Position</span><span>Seite</span>
        </div>
        <div class="divide-y divide-[color:var(--nx-line)]">
            @foreach ($d['keywords'] as $k)
                <div x-show="(! top || {{ $k['position'] }} <= 10) && (q === '' || @js(mb_strtolower($k['keyword'])).includes(q.toLowerCase()))"
                     class="grid grid-cols-[1fr_auto] items-center gap-x-3 gap-y-0.5 px-4 py-2.5 text-sm md:grid-cols-[1fr_110px_90px_180px]">
                    <span class="min-w-0 truncate">{{ $k['keyword'] }}</span>
                    <span class="hidden text-right tabular-nums text-[color:var(--nx-muted)] md:block">{{ $k['volume'] ? $n($k['volume']) : '–' }}</span>
                    <span class="flex items-center justify-end gap-1.5 tabular-nums">
                        @if ($k['change'])
                            <span class="text-[11px]" style="color: {{ $k['change'] > 0 ? 'var(--nx-success)' : 'var(--nx-danger)' }}">{{ $k['change'] > 0 ? '▲' : '▼' }}{{ abs($k['change']) }}</span>
                        @endif
                        <x-nx-badge :variant="$posBadge($k['position'])">{{ $k['position'] }}</x-nx-badge>
                    </span>
                    <span class="col-span-2 truncate text-xs text-[color:var(--nx-faint)] md:col-span-1">{{ $k['page'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
