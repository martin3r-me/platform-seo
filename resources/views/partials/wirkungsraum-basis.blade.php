{{-- Ordnen ① Basis — Index aller beteiligten eigenen URLs: SEO-Ziel (Dimensionen)
     + Basis-Cluster-Status + Aktionen. Der Handlungsort für die Basis-Arbeit im
     Wirkungsraum. Nutzt die vorhandene UrlSeoTarget-Modal + den Builder. --}}
<div class="mt-4">
    <p class="text-[12px] text-[color:var(--nx-muted)] mb-3">Jede beteiligte eigene Seite deklariert ihr <span class="font-medium text-[color:var(--nx-text)]">SEO-Ziel</span> (Basis · GEO · Anlass · Typ) — daraus baut DataForSEO den gesperrten <span class="font-medium text-[color:var(--nx-text)]">Basis-Cluster</span>. Darüber entstehen dann die Themenfelder.</p>

    @if($clusterFlash)
        <p class="text-[11px] mb-3" style="color:var(--nx-info)">{{ $clusterFlash }}</p>
    @endif

    @if($basisRows->isEmpty())
        <x-nx-empty>Keine eigenen URLs im Wirkungsraum.</x-nx-empty>
    @else
        <x-nx-card flush>
            <x-nx-table>
                <x-nx-table-header>
                    <x-nx-table-header-cell>Seite</x-nx-table-header-cell>
                    <x-nx-table-header-cell>SEO-Ziel</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="right">Basis-Cluster</x-nx-table-header-cell>
                    <x-nx-table-header-cell align="right">Aktion</x-nx-table-header-cell>
                </x-nx-table-header>
                <x-nx-table-body>
                    @foreach($basisRows as $row)
                        @php($u = $row['url'])
                        @php($basisDim = $row['dims']->get('basis'))
                        @php($hasBasis = $basisDim && $basisDim->isNotEmpty())
                        <x-nx-table-row>
                            <x-nx-table-cell>
                                <a href="{{ route('seo.urls.show', $u->id) }}" wire:navigate class="text-[color:var(--nx-text)] hover:text-[color:var(--nx-info)] hover:underline">{{ $u->display_label }}</a>
                            </x-nx-table-cell>
                            <x-nx-table-cell>
                                @if($hasBasis)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach(['basis', 'geo', 'anlass', 'typ', 'zielgruppe'] as $dk)
                                            @php($vals = $row['dims']->get($dk))
                                            @if($vals && $vals->isNotEmpty())
                                                @foreach($vals as $d)
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded {{ $dk === 'geo' ? 'bg-emerald-50 text-emerald-700' : 'bg-[color:var(--nx-line)] text-[color:var(--nx-muted)]' }}">{{ $dk === 'geo' ? '📍 ' : '' }}{{ $d->value }}</span>
                                                @endforeach
                                            @endif
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-[11px] text-[color:var(--nx-faint)]">— noch nicht definiert</span>
                                @endif
                            </x-nx-table-cell>
                            <x-nx-table-cell align="right">
                                @if($row['cluster'])
                                    <span class="text-[12px] text-[color:var(--nx-text)] tabular-nums">{{ number_format($row['kw']) }} KW</span><span class="text-[11px] text-[color:var(--nx-muted)]"> · {{ number_format($row['potential']) }}/Mon</span>
                                @else
                                    <span class="text-[11px] text-[color:var(--nx-faint)]">—</span>
                                @endif
                            </x-nx-table-cell>
                            <x-nx-table-cell align="right">
                                <span class="inline-flex items-center gap-2 justify-end">
                                    <button wire:click="$dispatch('open-url-target', { urlId: {{ $u->id }} })" class="text-[11px] text-[color:var(--nx-info)] hover:underline">{{ $hasBasis ? 'bearbeiten' : 'definieren' }}</button>
                                    @if($hasBasis)
                                        <button wire:click="buildBaseClusterFor({{ $u->id }})" wire:loading.attr="disabled" wire:target="buildBaseClusterFor" class="text-[11px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-info)]">{{ $row['cluster'] ? 'neu bauen' : 'bauen' }}</button>
                                    @endif
                                </span>
                            </x-nx-table-cell>
                        </x-nx-table-row>
                    @endforeach
                </x-nx-table-body>
            </x-nx-table>
        </x-nx-card>

        <div class="mt-3 flex items-center gap-2">
            <x-nx-button size="sm" wire:click="buildAllBaseClusters" wire:loading.attr="disabled" wire:target="buildAllBaseClusters">Alle Basis-Cluster bauen</x-nx-button>
            <span wire:loading wire:target="buildAllBaseClusters,buildBaseClusterFor" class="text-[11px] text-[color:var(--nx-muted)]">baue via DataForSEO…</span>
        </div>
    @endif

    <livewire:seo.url-seo-target />
</div>
