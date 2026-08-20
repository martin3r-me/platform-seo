{{-- Cluster-Ebene (Progressive Disclosure): pro Zeile nur Name · Typ · Chance +
     eine Aktion (übernehmen). Power-Metriken (Mix/Pot/Fit/Score/Intent) +
     Neben-Aktionen (Details/merken/integrieren/zuordnen/abstellen) auf „⋯ mehr".
     Erwartet: $nbIdx, $rooms (voll), $indices (Cluster-Indizes), $portfolio. --}}
@php($adoptRunning = ($portfolio->clustering_status ?? null) === 'running')
<div class="divide-y divide-[color:var(--nx-line)]">
    @foreach($indices as $roomIdx)
        @php($room = $rooms[$roomIdx] ?? null)
        @if($room)
            @php($rOwn = ($room['size'] ?? 0) - ($room['comp_count'] ?? 0))
            <div x-data="{ more: false }" wire:key="room-{{ $nbIdx }}-{{ $roomIdx }}">
                {{-- Primärzeile: das Nötige für die Entscheidung --}}
                <div class="flex items-center justify-between gap-3 px-2 py-1.5">
                    <span class="flex items-center gap-2 min-w-0">
                        @if(! empty($room['is_opportunity']))<span class="text-[9px] px-1.5 py-0.5 rounded shrink-0" style="background:color-mix(in srgb, var(--nx-warning) 16%, transparent);color:var(--nx-warning)" title="nur Wettbewerber ranken — erobern">Grau</span>
                        @elseif(! empty($room['is_rest']))<span class="text-[9px] px-1.5 py-0.5 rounded bg-[color:var(--nx-line)] text-[color:var(--nx-faint)] shrink-0">übrige</span>
                        @else<span class="text-[9px] px-1.5 py-0.5 rounded shrink-0" style="background:color-mix(in srgb, var(--nx-success) 16%, transparent);color:var(--nx-success)" title="baubar — keine eigene Seite rankt">Weißraum</span>@endif
                        <span class="text-[12px] text-[color:var(--nx-text)] truncate">{{ $room['label'] }}</span>
                        @if(! empty($room['near_cluster']))<span class="text-[10px] shrink-0" style="color:var(--nx-info)" title="nah an bestehendem Cluster">↗ {{ round($room['near_cluster']['sim'] * 100) }}%</span>@endif
                    </span>
                    <span class="flex items-center gap-2.5 shrink-0">
                        <span class="text-[11px] tabular-nums text-[color:var(--nx-faint)]">{{ $room['size'] }} KW</span>
                        <span class="text-[12px] tabular-nums font-medium" style="color:var(--nx-warning)" title="Chance = Mehr-Traffic/Monat (Potenzial − IST)">↑{{ number_format($room['gap'] ?? 0) }}</span>
                        <button wire:click="adoptRoom({{ $nbIdx }}, {{ $roomIdx }})" wire:loading.attr="disabled" @disabled($adoptRunning)
                                class="text-[11px] px-2 py-0.5 rounded bg-[color:var(--nx-text)] text-[color:var(--nx-bg)] disabled:opacity-40" title="SERP prüfen &amp; als Cluster übernehmen">übernehmen</button>
                        <button x-on:click="more = ! more" class="text-[13px] leading-none text-[color:var(--nx-faint)] hover:text-[color:var(--nx-text)] px-1" title="mehr: Metriken &amp; weitere Aktionen"><span x-text="more ? '▴' : '⋯'"></span></button>
                    </span>
                </div>

                {{-- Aufklappen: Power-Metriken + Neben-Aktionen --}}
                <div x-show="more" style="display:none" class="px-2 pb-2 pt-0.5">
                    <div class="flex items-center gap-4 flex-wrap text-[10px] text-[color:var(--nx-faint)] mb-1.5">
                        <span title="{{ $rOwn }} eigen · {{ $room['comp_count'] ?? 0 }} Wettbewerber-Lücke">
                            Mix
                            <span class="inline-block h-1.5 w-8 rounded-full overflow-hidden align-middle mx-0.5" style="background:color-mix(in srgb, var(--nx-danger) 30%, transparent)">
                                <span class="block h-full" style="background:var(--nx-success);width:{{ ($room['size'] ?? 0) > 0 ? round($rOwn / $room['size'] * 100) : 0 }}%"></span>
                            </span>
                            <span class="tabular-nums"><span style="color:var(--nx-success)">{{ $rOwn }}</span>/<span style="color:var(--nx-danger)">{{ $room['comp_count'] ?? 0 }}</span></span>
                        </span>
                        <span class="tabular-nums">Pot {{ number_format($room['potenzial'] ?? 0) }}</span>
                        <span class="tabular-nums" title="Nähe zum eigenen Kern-Thema">Fit {{ round(($room['fit'] ?? 0) * 100) }}%</span>
                        <span class="tabular-nums" title="Chance × Fit × Winnability">Score {{ number_format($room['score'] ?? 0) }}</span>
                        @if(! empty($room['intent']))<span>Intent: {{ $room['intent'] }}</span>@endif
                        @if(! empty($room['near_cluster']))<span style="color:var(--nx-info)">↗ nah an „{{ $room['near_cluster']['name'] }}"</span>@endif
                        @if(! empty($room['company']))<span style="color:var(--nx-info)">🏢 {{ $room['company']['domain'] }} {{ round($room['company']['sim'] * 100) }}%</span>@endif
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <button wire:click="openRoom({{ $nbIdx }}, {{ $roomIdx }})" class="text-[11px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Details</button>
                        <button wire:click="rememberRoom({{ $nbIdx }}, {{ $roomIdx }})" class="text-[11px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]" title="als Kandidaten-Cluster merken (ohne SERP)">merken</button>
                        @if(! empty($room['near_cluster']))
                            <button wire:click="integrateRoom({{ $nbIdx }}, {{ $roomIdx }}, {{ $room['near_cluster']['id'] }})" class="text-[11px] px-2 py-0.5 rounded" style="background:color-mix(in srgb, var(--nx-info) 14%, transparent);color:var(--nx-info)" title="in „{{ $room['near_cluster']['name'] }}" integrieren (statt neues Cluster)">integrieren</button>
                        @endif
                        @if(! empty($room['company']))
                            <button wire:click="assignRoomToCompany({{ $nbIdx }}, {{ $roomIdx }}, @js($room['company']['domain']))" class="text-[11px] px-2 py-0.5 rounded" style="background:color-mix(in srgb, var(--nx-info) 14%, transparent);color:var(--nx-info)" title="zu {{ $room['company']['domain'] }} zuordnen">→ {{ $room['company']['domain'] }}</button>
                        @endif
                        <button wire:click="retireRoom({{ $nbIdx }}, {{ $roomIdx }})" class="text-[11px] text-[color:var(--nx-faint)] hover:text-[color:var(--nx-danger)]" title="abstellen — Keywords stilllegen (umkehrbar)">abstellen</button>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>
