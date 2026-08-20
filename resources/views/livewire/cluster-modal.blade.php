{{-- Cluster-Inspektor (inline-Modal). Cluster-Name vorab in Variable, damit kein
     „->" im x-ui-modal-Tag-Attribut steht (Component-Tag-Parser-Falle). --}}
@php($clusterName = $cluster ? $cluster->name : 'Cluster')
<div>
    <x-ui-modal wire:model="show" title="{{ $clusterName }}">
        @if($cluster)
            @php($isBuild = ($cluster->origin ?? 'harvested') === 'build')
            <div class="space-y-4">
                {{-- Kopf: Herkunft · Status · Volumen --}}
                <div class="flex items-center gap-2 flex-wrap text-[11px] text-gray-500">
                    @if($isBuild)
                        <span class="text-[9px] uppercase tracking-wide px-1.5 py-0.5 rounded" style="background:color-mix(in srgb, var(--nx-info) 14%, transparent);color:var(--nx-info)">Bauziel</span>
                    @else
                        <span class="text-[9px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">geerntet</span>
                    @endif
                    <span>Status: {{ $cluster->status }}</span>
                    <span>· {{ number_format($keywords->count()) }} Keywords · {{ number_format($volume) }} Volumen</span>
                </div>

                {{-- Ziel-Seite (Pillar) --}}
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-[12px] font-semibold text-gray-700 mb-1">Ziel-Seite (Pillar)</div>
                    @if($cluster->pillarUrl)
                        <div class="text-[12px] text-gray-700 flex items-center gap-2 flex-wrap">
                            <a href="{{ route('seo.urls.show', $cluster->pillarUrl->id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $cluster->pillarUrl->display_label }}</a>
                            <button wire:click="removePillar" class="text-[11px] text-gray-400 hover:text-rose-600">entfernen</button>
                        </div>
                    @else
                        <div class="text-[12px] text-gray-400">Noch keine — {{ $isBuild ? 'neue Seite (Brief legt sie an)' : 'unten eine Seite wählen' }}.</div>
                    @endif
                    @if($candidates->isNotEmpty())
                        <select x-on:change="$wire.setPillarUrl($event.target.value)" class="mt-2 w-full text-[12px] border border-gray-300 rounded-lg px-3 py-2 bg-white">
                            <option value="">Ziel-Seite wählen (eigene Seiten, die schon ranken) …</option>
                            @foreach($candidates as $cand)
                                <option value="{{ $cand->id }}">{{ $cand->path ?: '/' }} — {{ $cand->kw_covered }} KW</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                {{-- Keywords + beste eigene Position --}}
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-1.5">Keywords ({{ $keywords->count() }}) · Vol · beste Position</div>
                    <div class="max-h-80 overflow-y-auto rounded-lg border border-gray-200">
                        <table class="w-full text-[12px]">
                            <tbody>
                                @foreach($keywords as $kw)
                                    @php($pos = $bestPos[$kw->id] ?? null)
                                    <tr class="border-b border-gray-50 last:border-0">
                                        <td class="py-1 px-2 text-gray-700">{{ $kw->keyword }}</td>
                                        <td class="py-1 px-2 text-right tabular-nums text-gray-500">{{ number_format($kw->search_volume) }}</td>
                                        <td class="py-1 px-2 text-right tabular-nums {{ $pos !== null && $pos <= 10 ? 'text-green-700' : ($pos !== null ? 'text-gray-500' : 'text-gray-300') }}">{{ $pos !== null ? '#' . $pos : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
        <x-slot name="footer">
            @if($cluster)
                <a href="{{ route('seo.clusters.show', $cluster->id) }}" wire:navigate class="text-[12px] text-indigo-600 hover:underline mr-auto">Zur vollen Cluster-Seite →</a>
            @endif
            <x-ui-button variant="secondary" size="sm" wire:click="close" type="button">Schließen</x-ui-button>
        </x-slot>
    </x-ui-modal>
</div>
