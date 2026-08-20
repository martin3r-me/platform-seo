{{-- SEO-Ziel je URL: Dimensionen als einfache Facetten. Footer-Aktionen per
     wire:click (x-ui-modal rendert den Footer außerhalb des <form>). --}}
<div>
    <x-ui-modal wire:model="show" title="SEO-Ziel">
        <div class="space-y-4">
            <p class="text-[11px] text-gray-400 -mt-1">
                Was diese Seite besitzen soll — als einfache Facetten. Daraus entstehen später Seed-Keywords und der gesperrte Basis-Cluster.
                @if($urlLabel !== '')<span class="text-gray-500">· {{ $urlLabel }}</span>@endif
            </p>

            @if($buildResult)
                <div class="text-[12px] rounded-md px-3 py-2 {{ $buildError ? 'bg-rose-50 text-rose-700' : 'bg-emerald-50 text-emerald-700' }}">{{ $buildResult }}</div>
            @endif

            {{-- Multi-Wert-Dimensionen: Basis (Kern) · Anlass · Typ · Zielgruppe --}}
            @foreach(['basis', 'anlass', 'typ', 'zielgruppe'] as $dim)
                @php($cfg = $catalog[$dim] ?? [])
                <div>
                    <div class="flex items-baseline gap-2">
                        <span class="text-[12px] font-semibold text-gray-700">{{ $cfg['label'] ?? $dim }}</span>
                        @if($cfg['kern'] ?? false)
                            <span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-indigo-50 text-indigo-600">Kern · Pflicht</span>
                        @endif
                    </div>
                    <p class="text-[10px] text-gray-400 mb-1">{{ $cfg['hint'] ?? '' }}</p>

                    <div class="flex flex-wrap gap-1 mb-1">
                        @forelse($values[$dim] as $i => $v)
                            <span class="inline-flex items-center gap-1 text-[11px] px-2 py-0.5 rounded-full bg-gray-100 text-gray-700">
                                {{ $v }}
                                <button wire:click="removeValue('{{ $dim }}', {{ $i }})" class="text-gray-400 hover:text-rose-600" title="entfernen">&times;</button>
                            </span>
                        @empty
                            <span class="text-[11px] text-gray-300">— noch keiner</span>
                        @endforelse
                    </div>

                    <div class="flex gap-1">
                        <input type="text" wire:model="buffers.{{ $dim }}" wire:keydown.enter.prevent="addValue('{{ $dim }}')"
                               placeholder="hinzufügen…"
                               class="flex-1 text-[12px] border border-gray-200 rounded px-2 py-1 focus:outline-none focus:border-indigo-400">
                        <button wire:click="addValue('{{ $dim }}')"
                                class="text-[12px] px-2.5 py-1 rounded border border-gray-200 text-gray-600 hover:border-indigo-400 hover:text-indigo-600">+</button>
                    </div>
                    @error($dim)<p class="text-[11px] text-rose-600 mt-1">{{ $message }}</p>@enderror
                </div>
            @endforeach

            {{-- GEO (single) aus dem Geo-Katalog --}}
            @php($geoCfg = $catalog['geo'] ?? [])
            <div>
                <span class="text-[12px] font-semibold text-gray-700">{{ $geoCfg['label'] ?? 'GEO' }}</span>
                <p class="text-[10px] text-gray-400 mb-1">{{ $geoCfg['hint'] ?? '' }}</p>

                @if($geoLocationId && $geoName)
                    <div class="flex items-center gap-2 text-[12px]">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700">📍 {{ $geoName }}</span>
                        <button wire:click="clearGeo" class="text-[11px] text-gray-400 hover:text-rose-600">entfernen</button>
                    </div>
                @else
                    <input type="text" wire:model.live.debounce.300ms="geoSearch"
                           placeholder="Ort/Region suchen (≥2 Zeichen)…"
                           class="w-full text-[12px] border border-gray-200 rounded px-2 py-1 focus:outline-none focus:border-indigo-400">
                    @if($geoResults->isNotEmpty())
                        <div class="mt-1 rounded border border-gray-200 divide-y divide-gray-50 max-h-40 overflow-auto">
                            @foreach($geoResults as $loc)
                                <button wire:click="selectGeo({{ $loc->id }})"
                                        class="w-full text-left px-2 py-1 text-[12px] hover:bg-gray-50 flex items-center justify-between">
                                    <span class="text-gray-700">{{ $loc->name }}</span>
                                    <span class="text-[10px] text-gray-400">{{ $loc->level ?? $loc->type }}</span>
                                </button>
                            @endforeach
                        </div>
                    @elseif(strlen(trim($geoSearch)) >= 2)
                        <p class="text-[11px] text-gray-400 mt-1">Nichts gefunden — Geo-Katalog schon synchronisiert? (<code>php artisan seo:geo-sync</code>)</p>
                    @endif
                @endif
            </div>
        </div>

        <x-slot name="footer">
            <x-ui-button variant="secondary" size="sm" wire:click="close" type="button">Abbrechen</x-ui-button>
            <x-ui-button variant="secondary" size="sm" wire:click="save" type="button">Nur speichern</x-ui-button>
            <x-ui-button variant="primary" size="sm" wire:click="buildBaseCluster" type="button"
                         wire:target="buildBaseCluster" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="buildBaseCluster">Speichern &amp; Basis-Cluster bauen</span>
                <span wire:loading wire:target="buildBaseCluster">baue via DataForSEO…</span>
            </x-ui-button>
        </x-slot>
    </x-ui-modal>
</div>
