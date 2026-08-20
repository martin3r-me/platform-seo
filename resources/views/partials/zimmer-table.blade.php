{{-- Cluster-Ebene — Entscheidungs-Werkzeug (domänen-agnostisch). Zeile zeigt
     Typ · Name · Größe · Chance; „Entscheiden" öffnet Klartext-Optionen mit
     Ziel & Folge (bauen / dazupacken / an Firma / merken / ignorieren),
     Metriken als Stütze. Erwartet: $nbIdx, $rooms, $indices, $portfolio. --}}
@php($adoptRunning = ($portfolio->clustering_status ?? null) === 'running')
@php($serpCt = (int) config('seo.cost_estimates.serp', 10))
<div class="divide-y divide-[color:var(--nx-line)]">
    @foreach($indices as $roomIdx)
        @php($room = $rooms[$roomIdx] ?? null)
        @if($room)
            @php($rOwn = ($room['size'] ?? 0) - ($room['comp_count'] ?? 0))
            @php($adoptCost = number_format(($room['size'] ?? 0) * $serpCt / 100, 2, ',', '.'))
            <div x-data="{ decide: false }" wire:key="room-{{ $nbIdx }}-{{ $roomIdx }}">
                {{-- Zeile --}}
                <div class="flex items-center justify-between gap-3 px-2 py-1.5">
                    <span class="flex items-center gap-2 min-w-0">
                        @if(! empty($room['is_opportunity']))<span class="text-[9px] px-1.5 py-0.5 rounded shrink-0" style="background:color-mix(in srgb, var(--nx-warning) 16%, transparent);color:var(--nx-warning)" title="nur Wettbewerber ranken — erobern">Grau</span>
                        @elseif(! empty($room['is_rest']))<span class="text-[9px] px-1.5 py-0.5 rounded bg-[color:var(--nx-line)] text-[color:var(--nx-faint)] shrink-0">übrige</span>
                        @else<span class="text-[9px] px-1.5 py-0.5 rounded shrink-0" style="background:color-mix(in srgb, var(--nx-success) 16%, transparent);color:var(--nx-success)" title="baubar — keine eigene Seite rankt">Weißraum</span>@endif
                        <span class="text-[12px] text-[color:var(--nx-text)] truncate">{{ $room['label'] }}</span>
                        @if(! empty($room['near_cluster']))<span class="text-[10px] shrink-0" style="color:var(--nx-info)" title="nah an bestehendem Cluster „{{ $room['near_cluster']['name'] }}"">↗ {{ round($room['near_cluster']['sim'] * 100) }}%</span>@endif
                    </span>
                    <span class="flex items-center gap-2.5 shrink-0">
                        <span class="text-[11px] tabular-nums text-[color:var(--nx-faint)]">{{ $room['size'] }} KW</span>
                        <span class="text-[12px] tabular-nums font-medium" style="color:var(--nx-warning)" title="Chance = Mehr-Traffic/Monat (Potenzial − IST)">↑{{ number_format($room['gap'] ?? 0) }}</span>
                        <button x-on:click="decide = ! decide" class="text-[11px] font-medium px-2 py-0.5 rounded border border-[color:var(--nx-line)] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]"><span x-text="decide ? 'schließen ▴' : 'Entscheiden ▾'"></span></button>
                    </span>
                </div>

                {{-- Entscheidungs-Panel: Klartext-Optionen mit Ziel & Folge --}}
                <div x-show="decide" style="display:none" class="px-2 pb-3 pt-0.5">
                    <div class="text-[10px] uppercase tracking-wide text-[color:var(--nx-faint)] mb-1">Was tun mit diesem Cluster?</div>
                    <div class="rounded-md border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)] overflow-hidden">
                        <button wire:click="adoptRoom({{ $nbIdx }}, {{ $roomIdx }})" wire:loading.attr="disabled" @disabled($adoptRunning)
                                class="w-full flex items-baseline justify-between gap-3 text-left px-2.5 py-1.5 hover:bg-[color:var(--nx-surface)] disabled:opacity-40">
                            <span class="text-[12px] font-medium text-[color:var(--nx-text)]">Neuen Cluster bauen</span>
                            <span class="text-[10px] text-[color:var(--nx-faint)] shrink-0">eigene Antwort-Einheit · prüft SERP (~{{ $adoptCost }} €)</span>
                        </button>
                        @if(! empty($room['near_cluster']))
                            <button wire:click="integrateRoom({{ $nbIdx }}, {{ $roomIdx }}, {{ $room['near_cluster']['id'] }})"
                                    class="w-full flex items-baseline justify-between gap-3 text-left px-2.5 py-1.5 hover:bg-[color:var(--nx-surface)]">
                                <span class="text-[12px] font-medium text-[color:var(--nx-text)]">Zu „{{ $room['near_cluster']['name'] }}" dazupacken</span>
                                <span class="text-[10px] text-[color:var(--nx-faint)] shrink-0">kein Doppel · kostenlos · Nähe {{ round($room['near_cluster']['sim'] * 100) }}%</span>
                            </button>
                        @endif
                        @if(! empty($room['company']))
                            <button wire:click="assignRoomToCompany({{ $nbIdx }}, {{ $roomIdx }}, @js($room['company']['domain']))"
                                    class="w-full flex items-baseline justify-between gap-3 text-left px-2.5 py-1.5 hover:bg-[color:var(--nx-surface)]">
                                <span class="text-[12px] font-medium text-[color:var(--nx-text)]">An {{ $room['company']['domain'] }} geben</span>
                                <span class="text-[10px] text-[color:var(--nx-faint)] shrink-0">Firma als Owner · Nähe {{ round($room['company']['sim'] * 100) }}%</span>
                            </button>
                        @endif
                        <button wire:click="rememberRoom({{ $nbIdx }}, {{ $roomIdx }})"
                                class="w-full flex items-baseline justify-between gap-3 text-left px-2.5 py-1.5 hover:bg-[color:var(--nx-surface)]">
                            <span class="text-[12px] font-medium text-[color:var(--nx-muted)]">Nur merken</span>
                            <span class="text-[10px] text-[color:var(--nx-faint)] shrink-0">vormerken, ohne SERP/Kosten</span>
                        </button>
                        <button wire:click="retireRoom({{ $nbIdx }}, {{ $roomIdx }})"
                                class="w-full flex items-baseline justify-between gap-3 text-left px-2.5 py-1.5 hover:bg-[color:var(--nx-surface)]">
                            <span class="text-[12px] font-medium text-[color:var(--nx-muted)]">Ignorieren</span>
                            <span class="text-[10px] text-[color:var(--nx-faint)] shrink-0">raus aus der Karte · umkehrbar</span>
                        </button>
                    </div>

                    {{-- Metriken als Entscheidungs-Stütze --}}
                    <div class="mt-1.5 flex items-center gap-3 flex-wrap text-[10px] text-[color:var(--nx-faint)]">
                        <span title="{{ $rOwn }} eigen · {{ $room['comp_count'] ?? 0 }} Wettbewerber-Lücke">Mix <span class="tabular-nums"><span style="color:var(--nx-success)">{{ $rOwn }}</span>/<span style="color:var(--nx-danger)">{{ $room['comp_count'] ?? 0 }}</span></span></span>
                        <span class="tabular-nums">Pot {{ number_format($room['potenzial'] ?? 0) }}</span>
                        <span class="tabular-nums" title="Nähe zum eigenen Kern-Thema">Fit {{ round(($room['fit'] ?? 0) * 100) }}%</span>
                        <span class="tabular-nums" title="Chance × Fit × Winnability">Score {{ number_format($room['score'] ?? 0) }}</span>
                        @if(! empty($room['intent']))<span>{{ $room['intent'] }}</span>@endif
                        <button wire:click="openRoom({{ $nbIdx }}, {{ $roomIdx }})" class="text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] underline underline-offset-2">Details (Keywords + rankende URLs)</button>
                    </div>
                </div>
            </div>
        @endif
    @endforeach
</div>
