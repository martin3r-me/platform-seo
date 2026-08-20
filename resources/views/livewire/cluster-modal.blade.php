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

                {{-- Ziel-Seite (Pillar) mit Erklärung --}}
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="text-[12px] font-semibold text-gray-700">Ziel-Seite (Pillar) — der Owner</div>
                    <p class="text-[11px] text-gray-400 mb-1.5">Die <span class="font-medium">eine</span> Seite (= die Owner-Firma), die dieses Thema besitzt und bespielt. <span class="font-medium">Genau eine</span> — unten eine wählen setzt sie (ersetzt die vorige). Das ist die Verteilen-Entscheidung: wem im Verbund gehört das Thema.</p>
                    @if($cluster->pillarUrl)
                        <div class="text-[12px] text-gray-700 flex items-center gap-2 flex-wrap">
                            <a href="{{ route('seo.urls.show', $cluster->pillarUrl->id) }}" wire:navigate class="text-indigo-600 hover:underline">{{ $cluster->pillarUrl->display_label }}</a>
                            <button wire:click="removePillar" class="text-[11px] text-gray-400 hover:text-rose-600">entfernen</button>
                        </div>
                    @else
                        <div class="text-[12px] text-gray-400">Noch keine — {{ $isBuild ? 'neue Seite (Brief legt sie an)' : 'unten eine rankende Seite als Ziel-Seite setzen' }}.</div>
                    @endif
                </div>

                {{-- Rankende Seiten — welche unserer Seiten (welcher Firmen) beteiligt sind --}}
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-1.5">Rankende Seiten ({{ $candidates->count() }}) — welche unserer Seiten hier auftauchen</div>
                    @if($candidates->isEmpty())
                        <div class="text-[12px] text-gray-400 rounded-lg border border-dashed border-gray-200 p-3">Noch keine eigene Seite rankt für dieses Thema — klassischer Neu-Bau-Fall.</div>
                    @else
                        @if($cannibalized > 0)
                            <div class="text-[11px] mb-1.5 rounded-md px-2 py-1.5" style="background:color-mix(in srgb, var(--nx-warning) 12%, transparent);color:var(--nx-warning)"><span class="font-medium">⚠ Kannibalisierung: {{ $cannibalized }} Keyword(s)</span> mit ≥2 eigenen Seiten im Top-20 — die nehmen sich gegenseitig das Signal. Auf <span class="font-medium">einen Owner</span> konsolidieren, die andere(n) re-targeten/umbauen. <span class="opacity-80">(Bei #1&amp;#2 dominierst du · tiefe/weit-auseinander sind egal.)</span></div>
                        @endif
                        <div class="rounded-lg border border-gray-200 divide-y divide-gray-50">
                            @foreach($candidates as $cand)
                                @php($isPillar = (int) $cluster->pillar_url_id === (int) $cand->id)
                                <div class="flex items-center justify-between gap-2 px-2 py-1.5 text-[12px]">
                                    <a href="{{ route('seo.urls.show', $cand->id) }}" wire:navigate class="min-w-0 truncate hover:underline">
                                        <span class="text-gray-800 font-medium">{{ $cand->domain }}</span><span class="text-gray-400">{{ $cand->path !== '/' ? $cand->path : '' }}</span>
                                    </a>
                                    <span class="shrink-0 flex items-center gap-2 text-[11px]">
                                        <span class="tabular-nums text-gray-400">{{ $cand->kw_covered }} KW · #{{ $cand->best }}</span>
                                        @if($isPillar)
                                            <span class="text-[9px] uppercase tracking-wide px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600">Owner</span>
                                        @else
                                            <button wire:click="setPillarUrl({{ $cand->id }})" class="text-gray-500 hover:text-indigo-600">→ als Owner setzen</button>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Keywords + beste eigene Position + entfernen (volle Höhe, kein Cap) --}}
                <div>
                    <div class="text-[10px] uppercase tracking-wide text-gray-400 mb-1.5">Keywords ({{ $keywords->count() }}) · Vol · beste Position · ignorieren</div>
                    <p class="text-[10px] text-gray-400 mb-1.5 normal-case">Wir ranken evtl. incidental (z. B. für fremde Marken) — „ignorieren" heißt nicht un-ranken, sondern: interessiert uns nicht, raus aus dem Arbeitsset (umkehrbar in „Abgestellt").</p>
                    <div class="rounded-lg border border-gray-200">
                        <table class="w-full text-[12px]">
                            <tbody>
                                @foreach($keywords as $kw)
                                    @php($pos = $bestPos[$kw->id] ?? null)
                                    <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/60">
                                        <td class="py-1 px-2 text-gray-700">{{ $kw->keyword }}</td>
                                        <td class="py-1 px-2 text-right tabular-nums text-gray-500">{{ number_format($kw->search_volume) }}</td>
                                        <td class="py-1 px-2 text-right tabular-nums {{ $pos !== null && $pos <= 10 ? 'text-green-700' : ($pos !== null ? 'text-gray-500' : 'text-gray-300') }}">{{ $pos !== null ? '#' . $pos : '—' }}</td>
                                        <td class="py-1 px-2 text-right"><button wire:click="ignoreKeyword({{ $kw->id }})" class="text-[11px] text-gray-300 hover:text-rose-600" title="interessiert uns nicht — raus aus dem Arbeitsset (umkehrbar)">ignorieren</button></td>
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
