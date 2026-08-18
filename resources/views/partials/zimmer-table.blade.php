{{-- Cluster-Tabelle. Erwartet: $nbIdx, $rooms (voll), $indices (zu zeigende Cluster-Indizes), $portfolio. --}}
<div class="overflow-x-auto">
    <table class="w-full text-[12px]" style="min-width:780px">
        <thead>
            <tr class="text-[9px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                <th class="text-left font-medium py-1 px-2">Cluster</th>
                <th class="text-left font-medium py-1 px-2">Typ</th>
                <th class="text-left font-medium py-1 px-2">Intent</th>
                <th class="text-right font-medium py-1 px-2" title="Besitz-Mix: eigen (rankt) / Wettbewerber-Lücke">KWs · Mix</th>
                <th class="text-right font-medium py-1 px-2">Pot</th>
                <th class="text-right font-medium py-1 px-2" title="Nähe zum eigenen Kern-Thema">Fit</th>
                <th class="text-right font-medium py-1 px-2">↑Chance</th>
                <th class="text-right font-medium py-1 px-2" title="Chance × Fit × Winnability">Score</th>
                <th class="text-right font-medium py-1 px-2">Aktion</th>
            </tr>
        </thead>
        <tbody>
            @foreach($indices as $roomIdx)
                @php($room = $rooms[$roomIdx] ?? null)
                @if($room)
                    <tr class="border-b border-gray-50 last:border-0 hover:bg-white">
                        <td class="py-1.5 px-2" style="max-width:220px">
                            <div class="text-gray-700" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $room['label'] }}@if(! empty($room['pattern']))<span class="text-[8px] uppercase px-1 rounded bg-gray-200 text-gray-600 ml-1">Muster</span>@endif</div>
                            @if(! empty($room['near_cluster']))<div class="text-[10px] text-teal-700" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="Zentroid-Nähe zu bestehendem Cluster">↗ nah an „{{ $room['near_cluster']['name'] }}" {{ round($room['near_cluster']['sim'] * 100) }}%</div>@endif
                            @if(! empty($room['company']))<div class="text-[10px]" style="color:#6366f1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="Firmen-Feld im Verbund">🏢 {{ $room['company']['domain'] }} {{ round($room['company']['sim'] * 100) }}% · <button wire:click="assignRoomToCompany({{ $nbIdx }}, {{ $roomIdx }}, @js($room['company']['domain']))" class="underline hover:no-underline">zuordnen</button></div>@endif
                        </td>
                        <td class="py-1.5 px-2">
                            @if(! empty($room['is_opportunity']))<span class="text-[9px] px-1.5 py-0.5 rounded" style="background:#b3a79433;color:#8a7a63">Grau</span>
                            @elseif(! empty($room['is_rest']))<span class="text-[9px] text-gray-400">übrige</span>
                            @else<span class="text-[9px] px-1.5 py-0.5 rounded bg-green-100 text-green-700">Weißraum</span>@endif
                        </td>
                        <td class="py-1.5 px-2">@if(! empty($room['intent']))<span class="text-[9px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">{{ $room['intent'] }}</span>@else<span class="text-gray-300 text-[10px]">—</span>@endif</td>
                        <td class="py-1.5 px-2 text-right tabular-nums text-gray-500">
                            @php($rOwn = ($room['size'] ?? 0) - ($room['comp_count'] ?? 0))
                            <span class="inline-flex items-center gap-1 justify-end" title="{{ $rOwn }} eigen · {{ $room['comp_count'] ?? 0 }} Wettbewerber-Lücke (von {{ $room['size'] }})">
                                <span class="inline-block h-1.5 w-7 rounded-full overflow-hidden align-middle" style="background:#fecdd3">
                                    <span class="block h-full" style="background:#0f766e;width:{{ ($room['size'] ?? 0) > 0 ? round($rOwn / $room['size'] * 100) : 0 }}%"></span>
                                </span>
                                <span class="text-[11px]"><span style="color:#0f766e">{{ $rOwn }}</span><span class="text-gray-300">/</span><span style="color:#e11d48">{{ $room['comp_count'] ?? 0 }}</span></span>
                            </span>
                        </td>
                        <td class="py-1.5 px-2 text-right tabular-nums text-gray-600">{{ number_format($room['potenzial'] ?? 0) }}</td>
                        <td class="py-1.5 px-2 text-right tabular-nums" style="color:{{ ($room['fit'] ?? 0) >= 0.6 ? '#15803d' : (($room['fit'] ?? 0) >= 0.35 ? '#b45309' : '#9ca3af') }}" title="Nähe zum eigenen Kern-Thema">{{ round(($room['fit'] ?? 0) * 100) }}%</td>
                        <td class="py-1.5 px-2 text-right tabular-nums" style="color:#e11d48">↑{{ number_format($room['gap'] ?? 0) }}</td>
                        <td class="py-1.5 px-2 text-right tabular-nums font-semibold text-gray-800" title="Chance × Fit × Winnability">{{ number_format($room['score'] ?? 0) }}</td>
                        <td class="py-1.5 px-2 text-right whitespace-nowrap">
                            <button wire:click="openRoom({{ $nbIdx }}, {{ $roomIdx }})" class="text-[11px] text-gray-400 hover:text-gray-700 mr-1.5">Details</button>
                            <button wire:click="rememberRoom({{ $nbIdx }}, {{ $roomIdx }})" class="text-[11px] text-gray-500 hover:text-gray-800 mr-1.5" title="als Kandidaten-Cluster merken (ohne SERP)">merken</button>
                            @if(! empty($room['near_cluster']))
                                <button wire:click="integrateRoom({{ $nbIdx }}, {{ $roomIdx }}, {{ $room['near_cluster']['id'] }})" class="text-[11px] px-2 py-0.5 rounded bg-teal-600 text-white mr-1.5" title="in „{{ $room['near_cluster']['name'] }}" integrieren (statt neues Cluster)">integrieren</button>
                            @endif
                            <button wire:click="adoptRoom({{ $nbIdx }}, {{ $roomIdx }})" wire:loading.attr="disabled" @disabled(($portfolio->clustering_status ?? null) === 'running')
                                    class="text-[11px] px-2 py-0.5 rounded bg-gray-900 text-white disabled:opacity-40 mr-1.5" title="SERP prüfen & als Cluster übernehmen">übernehmen</button>
                            <button wire:click="retireRoom({{ $nbIdx }}, {{ $roomIdx }})" class="text-[11px] text-gray-400 hover:text-rose-600" title="abstellen — Keywords stilllegen (umkehrbar)">abstellen</button>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
