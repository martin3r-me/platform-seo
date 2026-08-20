{{-- v2-Sicht: Entitäten des Wirkungsraums — Angebot (extrahiert) UND Nachfrage
     (aus Clustern), mit Präsenz + Share of Answer + Lücken. Erwartet: $entities, $entityFlash. --}}
<div class="mb-3 flex items-start justify-between gap-4 flex-wrap">
    <div class="max-w-2xl">
        <h2 class="text-[13px] font-semibold text-gray-700">Entitäten <span class="text-gray-400 font-normal">· besitzen wir das gefragte Feld?</span></h2>
        {{-- Faden-Verortung: wo im roten Faden diese Sicht liegt + wohin sie speist --}}
        <div class="text-[10px] text-gray-400 mt-1 flex items-center gap-1.5 flex-wrap">
            <span>Roter Faden:</span>
            <span class="px-1 py-0.5 rounded bg-gray-100 text-gray-500">Ordnen · Themen</span>
            <span>→</span>
            <span class="px-1 py-0.5 rounded bg-indigo-50 text-indigo-600 font-medium">Entitäten · besitzen wir sie?</span>
            <span>→</span>
            <button wire:click="setView('act')" class="px-1 py-0.5 rounded bg-gray-100 text-gray-500 hover:text-gray-800 underline-offset-2 hover:underline">Maßnahmen · Lücken schließen</button>
        </div>
        <p class="text-[11px] text-gray-500 mt-1.5 leading-relaxed">Jedes Thema von zwei Seiten: <span style="color:#0f766e" class="font-medium">Angebot</span> = wir haben eine Antwort (aus dem echten Seiteninhalt) · <span style="color:#b45309" class="font-medium">Nachfrage</span> = es wird gesucht (aus deinen Clustern). <span class="font-medium">Beantwortet</span> = wir besitzen es; <span class="font-medium" style="color:#b45309">Lücke</span> = gefragt, aber (noch) keine Antwort → baubar. <span class="font-medium">SERP</span> = klassische Suche · <span class="font-medium">AI</span> = zitiert die KI uns.</p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <button wire:click="syncDemandEntities" wire:loading.attr="disabled" wire:target="syncDemandEntities"
                class="text-[12px] font-medium px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
            <span wire:loading.remove wire:target="syncDemandEntities">↧ Nachfrage laden</span>
            <span wire:loading wire:target="syncDemandEntities">lädt…</span>
        </button>
        <button wire:click="extractAllAnswerUnits" wire:loading.attr="disabled" wire:target="extractAllAnswerUnits"
                class="text-[12px] font-medium px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50" title="Alle Mitglieder-URLs extrahieren (Hintergrund)">
            <span wire:loading.remove wire:target="extractAllAnswerUnits">✨ Alle extrahieren</span>
            <span wire:loading wire:target="extractAllAnswerUnits">startet…</span>
        </button>
        <button wire:click="mergeEntities" wire:loading.attr="disabled" wire:target="mergeEntities"
                class="text-[12px] font-medium px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50" title="Semantisch gleiche Entitäten (Angebot ↔ Nachfrage) zusammenführen">
            <span wire:loading.remove wire:target="mergeEntities">🔗 Zusammenführen</span>
            <span wire:loading wire:target="mergeEntities">führt zusammen…</span>
        </button>
    </div>
</div>

@if($entityFlash)
    <p class="text-[11px] mb-3" style="color:#4f46e5">{{ $entityFlash }}</p>
@endif

@if(empty($entities['rows']))
    <div class="bg-white rounded-lg border border-dashed border-gray-200 p-6 text-[12px] text-gray-500">Noch keine Entitäten. „↧ Nachfrage laden" holt die Themen (Cluster), „✨ Alle extrahieren" liest den Inhalt aller Mitglieder-Seiten.</div>
@else
    @if(($entities['share'] ?? null) !== null)
        <div class="mb-3 bg-indigo-50/40 border border-indigo-100 rounded-lg px-4 py-2.5 flex items-center gap-6 flex-wrap">
            <div>
                <span class="text-[22px] font-semibold tabular-nums" style="color:#4f46e5">{{ $entities['share'] }}%</span>
                <span class="text-[10px] uppercase tracking-wide text-gray-400 ml-1">Share of Answer</span>
            </div>
            <div class="text-[11px] text-gray-600 flex-1 min-w-[220px]">
                <span class="font-medium">Anteil der gefragten Themen, bei denen wir präsent sind</span> (SERP oder KI) — dein Besitz des Feldes.
                <span class="text-gray-400">· {{ $entities['present'] }}/{{ $entities['total'] }} präsent · {{ $entities['answered'] }} beantwortet · <span style="color:#b45309">{{ $entities['total'] - $entities['answered'] }} Lücke(n)</span></span>
            </div>
        </div>
    @endif
    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-[12px]" style="min-width:760px">
            <thead>
                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                    <th class="text-left px-3 py-2">Entität</th>
                    <th class="text-left px-3 py-2">Typ</th>
                    <th class="text-right px-3 py-2">Nachfrage</th>
                    <th class="text-center px-3 py-2">Status</th>
                    <th class="text-center px-3 py-2">SERP</th>
                    <th class="text-center px-3 py-2">AI</th>
                    <th class="text-right px-3 py-2">Aktion</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entities['rows'] as $e)
                    <tr class="border-b border-gray-50 last:border-0 {{ $e['answered'] ? '' : 'bg-amber-50/30' }}">
                        <td class="px-3 py-2 text-gray-700">{{ $e['name'] }}</td>
                        <td class="px-3 py-2">@if($e['type'])<span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded bg-gray-100 text-gray-500">{{ $e['type'] }}</span>@else<span class="text-gray-300">—</span>@endif</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ $e['demand'] > 0 ? number_format($e['demand']) : '—' }}</td>
                        <td class="px-3 py-2 text-center">
                            @if($e['answered'])<span class="text-[10px] px-1.5 py-0.5 rounded bg-green-50 text-green-700 border border-green-100">beantwortet · {{ $e['units'] }}</span>
                            @else<span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-100">Lücke</span>@endif
                        </td>
                        <td class="px-3 py-2 text-center">@if($e['serp'])<span class="text-[10px] px-1.5 py-0.5 rounded bg-green-50 text-green-700 border border-green-100">#{{ $e['serp_pos'] ?? '?' }}</span>@else<span class="text-gray-300">—</span>@endif</td>
                        <td class="px-3 py-2 text-center">@if($e['ai'])<span class="text-[10px] px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-100">zitiert</span>@else<span class="text-gray-300">—</span>@endif</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if($e['answered'])
                                <button wire:click="probeEntityAi({{ $e['entity_id'] }})" wire:loading.attr="disabled" wire:target="probeEntityAi" class="text-[11px] text-gray-500 hover:text-indigo-700 mr-2" title="KI fragen, ob wir erwähnt werden (Modell-Wissen)">🔮 AI fragen</button>
                                <button wire:click="startExperiment({{ $e['entity_id'] }})" class="text-[11px] px-2 py-0.5 rounded bg-gray-900 text-white" title="Optimierung als messbares Experiment starten">Experiment</button>
                            @else
                                <span class="text-[11px] text-gray-400">→ Antwort bauen</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-2 flex items-center justify-between gap-3 flex-wrap">
        <p class="text-[10px] text-gray-400 max-w-2xl">Zeilen mit <span style="color:#b45309">Lücke</span> = Nachfrage da, aber noch keine eigene Antwort → Content bauen. 🔮 „AI fragen" = Modell-Wissen (kein Live-Web). „Experiment" sichert die Baseline für die Wirkungsmessung.</p>
        <button wire:click="setView('act')" class="shrink-0 text-[11px] font-medium text-indigo-600 hover:text-indigo-800">→ Lücken &amp; GEO-Chancen werden im Posteingang zu Maßnahmen</button>
    </div>
@endif
