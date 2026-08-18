{{-- v2-Sicht: besessene Entitäten des Wirkungsraums + Multi-Surface-Präsenz +
     Share of Answer. Erwartet: $entities, $entityFlash. --}}
<div class="mb-3 flex items-end justify-between gap-4 flex-wrap">
    <div>
        <h2 class="text-[13px] font-semibold text-gray-700">Entitäten <span class="text-gray-400 font-normal">· was wir autoritativ beantworten</span></h2>
        <p class="text-[11px] text-gray-400 mt-0.5 max-w-2xl">Die besessenen Antworten dieses Wirkungsraums (aus dem echten Seiteninhalt), mit Präsenz je Surface. <span class="font-medium">SERP</span> = klassische Suche · <span class="font-medium">AI</span> = KI-Zitat.</p>
    </div>
    @if(($entities['share'] ?? null) !== null)
        <div class="text-right shrink-0">
            <div class="text-[22px] font-semibold tabular-nums" style="color:#4f46e5">{{ $entities['share'] }}%</div>
            <div class="text-[10px] uppercase tracking-wide text-gray-400">Share of Answer · {{ $entities['present'] }}/{{ $entities['total'] }} präsent</div>
        </div>
    @endif
</div>

@if($entityFlash)
    <p class="text-[11px] mb-3" style="color:#4f46e5">{{ $entityFlash }}</p>
@endif

@if(empty($entities['rows']))
    <div class="bg-white rounded-lg border border-dashed border-gray-200 p-6 text-[12px] text-gray-500">Noch keine Entitäten — am URL-Detail „Antwort-Einheiten extrahieren" (oder die Pipeline läuft ~monatlich). Danach je Entität die Präsenz messen.</div>
@else
    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-[12px]" style="min-width:720px">
            <thead>
                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                    <th class="text-left px-3 py-2">Entität</th>
                    <th class="text-left px-3 py-2">Typ</th>
                    <th class="text-right px-3 py-2">Bausteine</th>
                    <th class="text-center px-3 py-2">SERP</th>
                    <th class="text-center px-3 py-2">AI</th>
                    <th class="text-right px-3 py-2">Aktion</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entities['rows'] as $e)
                    <tr class="border-b border-gray-50 last:border-0">
                        <td class="px-3 py-2 text-gray-700">{{ $e['name'] }}</td>
                        <td class="px-3 py-2">@if($e['type'])<span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-gray-100 text-gray-500">{{ $e['type'] }}</span>@else<span class="text-gray-300">—</span>@endif</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $e['units'] }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($e['serp'])<span class="text-[10px] px-1.5 py-0.5 rounded bg-green-50 text-green-700 border border-green-100">#{{ $e['serp_pos'] ?? '?' }}</span>@else<span class="text-gray-300">—</span>@endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            @if($e['ai'])<span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">zitiert</span>@else<span class="text-gray-300">—</span>@endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            <button wire:click="probeEntityAi({{ $e['entity_id'] }})" wire:loading.attr="disabled" wire:target="probeEntityAi" class="text-[11px] text-gray-500 hover:text-indigo-700 mr-2" title="KI fragen, ob wir erwähnt werden (Modell-Wissen)">🔮 AI fragen</button>
                            <button wire:click="startExperiment({{ $e['entity_id'] }})" class="text-[11px] px-2 py-0.5 rounded bg-gray-900 text-white" title="Optimierung als messbares Experiment starten (Baseline sichern)">Experiment</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-[10px] text-gray-400">🔮 „AI fragen" = Modell-Wissen (kein Live-Web) — GEO-Frühindikator. „Experiment" sichert die Baseline; nach der Umsetzung misst die Pipeline das Ergebnis (Verdict).</p>
@endif
