{{-- Ordnen-Station „Basis" — Index aller beteiligten eigenen URLs (Notion-Stil,
     luftig): SEO-Ziel-Chips + Basis-Cluster-Status + Aktionen. Nutzt die vorhandene
     UrlSeoTarget-Modal + buildBaseClusterFor/buildAllBaseClusters. --}}
<div class="mt-5">
    {{-- Kopf: Erklärung + „Alle bauen" --}}
    <div class="flex items-start justify-between gap-6 mb-4">
        <p class="text-[12px] text-[color:var(--nx-muted)] leading-relaxed max-w-xl">
            Jede beteiligte Seite deklariert ihr <span class="font-medium text-[color:var(--nx-text)]">SEO-Ziel</span> —
            daraus baut DataForSEO den gesperrten <span class="font-medium text-[color:var(--nx-text)]">Basis-Cluster</span>.
            Darüber entstehen dann die Themenfelder.
        </p>
        @if($basisRows->isNotEmpty())
            <x-nx-button size="sm" wire:click="buildAllBaseClusters" wire:loading.attr="disabled" wire:target="buildAllBaseClusters" class="shrink-0">Alle bauen</x-nx-button>
        @endif
    </div>

    {{-- Status/Ladezustand --}}
    @if($clusterFlash)
        <div class="text-[12px] rounded-md px-3 py-2 mb-4" style="background:color-mix(in srgb, var(--nx-info) 10%, transparent);color:var(--nx-info)">{{ $clusterFlash }}</div>
    @endif
    <div wire:loading wire:target="buildAllBaseClusters,buildBaseClusterFor" class="text-[12px] text-[color:var(--nx-muted)] mb-4">baue via DataForSEO…</div>

    @if($basisRows->isEmpty())
        <x-nx-empty>Keine eigenen URLs im Wirkungsraum.</x-nx-empty>
    @else
        <x-nx-card flush>
            <div class="divide-y divide-[color:var(--nx-line)]">
                @foreach($basisRows as $row)
                    @php($u = $row['url'])
                    <div class="group flex items-center gap-5 px-4 py-3 transition-colors hover:bg-[color:color-mix(in_srgb,var(--nx-line)_35%,transparent)]">
                        {{-- Links: URL + Ziel-Chips --}}
                        <div class="min-w-0 flex-1">
                            <a href="{{ route('seo.urls.show', $u->id) }}" wire:navigate class="block text-[13px] font-medium text-[color:var(--nx-text)] hover:text-[color:var(--nx-info)] truncate">{{ $u->display_label }}</a>
                            @if($row['hasBasis'])
                                <div class="flex flex-wrap items-center gap-1 mt-1.5">
                                    @foreach(['basis', 'geo', 'anlass', 'typ', 'zielgruppe'] as $dk)
                                        @php($vals = $row['dims']->get($dk))
                                        @if($vals && $vals->isNotEmpty())
                                            @foreach($vals as $d)
                                                @php($label = $dk === 'geo' ? '📍 '.trim(explode(',', (string) $d->value)[0]) : $d->value)
                                                <span class="text-[10.5px] leading-none px-1.5 py-1 rounded-md {{ $dk === 'geo' ? 'bg-emerald-50 text-emerald-700' : 'bg-[color:var(--nx-line)] text-[color:var(--nx-muted)]' }}">{{ $label }}</span>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </div>
                            @else
                                <div class="text-[11px] text-[color:var(--nx-faint)] mt-0.5">noch nicht definiert</div>
                            @endif
                        </div>

                        {{-- Mitte: Basis-Cluster-Stat --}}
                        <div class="shrink-0 w-[120px] text-right">
                            @if($row['cluster'])
                                <div class="text-[13px] text-[color:var(--nx-text)] tabular-nums">{{ number_format($row['kw']) }} <span class="text-[10px] text-[color:var(--nx-faint)]">KW</span></div>
                                <div class="text-[11px] text-[color:var(--nx-muted)] tabular-nums">{{ number_format($row['potential']) }}/Mon</div>
                            @else
                                <span class="text-[12px] text-[color:var(--nx-faint)]">–</span>
                            @endif
                        </div>

                        {{-- Rechts: Aktionen (dezent, bei Hover deutlicher) --}}
                        <div class="shrink-0 w-[128px] flex items-center justify-end gap-3 text-[11px]">
                            <button wire:click="$dispatch('open-url-target', { urlId: {{ $u->id }} })" class="text-[color:var(--nx-info)] opacity-80 group-hover:opacity-100 hover:underline">{{ $row['hasBasis'] ? 'bearbeiten' : 'definieren' }}</button>
                            @if($row['hasBasis'])
                                <button wire:click="buildBaseClusterFor({{ $u->id }})" wire:loading.attr="disabled" wire:target="buildBaseClusterFor" class="font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">{{ $row['cluster'] ? 'neu bauen' : 'bauen' }}</button>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-nx-card>
    @endif

    <livewire:seo.url-seo-target />
</div>
