{{-- Orchestrierungs-Board: Thema x Property. Owner kueren, Kannibalisierung
     aufloesen, Pillar-Kandidaten sehen. Erwartet: $board. --}}
<div class="mb-3">
    <h2 class="text-[13px] font-semibold text-gray-700">Orchestrierung — welches Thema für wen</h2>
    <p class="text-[11px] text-gray-400 mt-0.5 max-w-2xl">Ein Thema = ein Owner. Wo mehrere Brands ranken → Owner küren, Rest differenzieren. <span style="color:#7c3aed">✦ Pillar-Kandidat</span> = hohe Kopf-Nachfrage ohne natürlichen Owner → zentrale Seite erwägen.</p>
</div>

@if(empty($board['rows']))
    <div class="bg-white rounded-lg border border-gray-200 p-6 text-[12px] text-gray-500">Noch keine Cluster mit rankenden Properties — erst in „Ordnen" Themen bauen und übernehmen.</div>
@else
    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-[12px]" style="min-width:780px">
            <thead>
                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                    <th class="text-left px-3 py-2">Thema</th>
                    <th class="text-right px-3 py-2">Nachfrage</th>
                    <th class="text-left px-3 py-2">Kandidaten (Property · Pos)</th>
                    <th class="text-left px-3 py-2">Owner</th>
                    <th class="text-left px-3 py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($board['rows'] as $row)
                    <tr class="border-b border-gray-50 last:border-0 align-top">
                        <td class="px-3 py-2">
                            <a href="{{ route('seo.clusters.show', $row['cluster_id']) }}" wire:navigate class="text-gray-700 hover:underline">{{ $row['name'] }}</a>
                            @if($row['pillar_candidate'])<span class="ml-1 text-[9px] uppercase tracking-wide px-1 py-0.5 rounded" style="background:#ede9fe;color:#7c3aed">✦ Pillar</span>@endif
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-600">{{ number_format($row['demand']) }}</td>
                        <td class="px-3 py-2">
                            <div class="flex flex-wrap gap-1" style="max-width:280px">
                                @foreach($row['candidates'] as $c)
                                    <span class="inline-flex items-center gap-1 text-[11px] px-1.5 py-0.5 rounded bg-gray-50 border {{ $row['owner_id'] === $c['url_id'] ? 'border-teal-300' : 'border-gray-200' }}" title="{{ $c['label'] }} · Position {{ $c['pos'] }}">
                                        <span class="truncate" style="max-width:120px">{{ $c['label'] }}</span><span class="text-gray-400 tabular-nums">#{{ $c['pos'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </td>
                        <td class="px-3 py-2">
                            <select x-on:change="$wire.setClusterOwner({{ $row['cluster_id'] }}, $event.target.value)" class="text-[12px] border border-gray-300 rounded px-2 py-1 bg-white" style="max-width:180px">
                                <option value="" @selected(! $row['owner_id'])>— kein Owner —</option>
                                @foreach($row['candidates'] as $c)
                                    <option value="{{ $c['url_id'] }}" @selected($row['owner_id'] === $c['url_id'])>{{ $c['label'] }}</option>
                                @endforeach
                                @if($row['owner_id'] && ! collect($row['candidates'])->contains('url_id', $row['owner_id']))
                                    <option value="{{ $row['owner_id'] }}" selected>{{ $row['owner_label'] }} (rankt nicht)</option>
                                @endif
                            </select>
                        </td>
                        <td class="px-3 py-2">
                            @if($row['conflict'])
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-100">⚠ {{ $row['candidate_count'] }} konkurrieren — Owner küren</span>
                            @elseif($row['owner_not_ranking'])
                                <span class="text-[11px] px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 border border-amber-100">Owner rankt (noch) nicht</span>
                            @elseif($row['owner_id'])
                                <span class="text-[11px] text-green-700">✓ Owner gekürt</span>
                            @else
                                <span class="text-[11px] text-gray-400">frei</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-[10px] text-gray-400">Owner küren schreibt die Pillar-Zuordnung (ein Thema = eine Seite). Für die KI-Aussteuerung (Split · Cross-Link · Pillar-Text) oben „🤖 Verteilung vorschlagen".</p>
@endif
