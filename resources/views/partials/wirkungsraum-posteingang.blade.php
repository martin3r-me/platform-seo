{{-- Maßnahmen-Posteingang: die Zentrale des Wirkungsraums. Vorschläge triagieren
     (annehmen -> Queue -> Flynk / ablehnen + Grund -> bleibt als Kontext).
     Erwartet: $measures, $measureFlash. --}}
<div class="mb-3 flex items-start justify-between gap-4 flex-wrap">
    <div>
        <h2 class="text-[13px] font-semibold text-gray-700">Posteingang</h2>
        <p class="text-[11px] text-gray-400 mt-0.5 max-w-2xl">Die Zentrale: vorgeschlagene Maßnahmen annehmen (→ Queue → Flynk) oder begründet ablehnen (bleibt als Wirkungsraum-Kontext). Nach Wert sortiert.</p>
    </div>
    <button wire:click="generateMeasures" wire:loading.attr="disabled" wire:target="generateMeasures"
            class="shrink-0 text-[12px] font-medium px-3 py-1.5 rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">
        <span wire:loading.remove wire:target="generateMeasures">↻ Vorschläge holen</span>
        <span wire:loading wire:target="generateMeasures">prüfe…</span>
    </button>
</div>

@if($measureFlash)
    <p class="text-[11px] mb-3" style="color:#0f766e">{{ $measureFlash }}</p>
@endif

{{-- NEU — die eigentliche Triage --}}
@php($proposed = $measures->where('status', 'proposed'))
@if($proposed->isEmpty())
    <div class="bg-white rounded-lg border border-dashed border-gray-200 p-6 text-[12px] text-gray-500">Posteingang leer. „↻ Vorschläge holen" prüft die Signale (Kannibalisierung, Pillar-Kandidaten …) auf neue Maßnahmen.</div>
@else
    <div class="space-y-2">
        @foreach($proposed as $m)
            @php($routeColor = ['flynk' => '#166EE1', 'internal' => '#6b7280', 'human' => '#7c3aed'][$m->route] ?? '#6b7280')
            <div x-data="{ rej: false, reason: '' }" class="bg-white rounded-lg border border-gray-200 p-3.5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap mb-1">
                            <span class="text-[9px] uppercase tracking-wide px-1.5 py-0.5 rounded" style="background:color-mix(in srgb, {{ $routeColor }} 12%, transparent);color:{{ $routeColor }}">{{ $m->typeLabel() }}</span>
                            <span class="text-[9px] uppercase tracking-wide text-gray-400">→ {{ $m->route }}</span>
                            @if($m->score > 0)<span class="text-[10px] text-gray-400 tabular-nums">Wert {{ number_format($m->score) }}</span>@endif
                        </div>
                        <div class="text-[13px] font-medium text-gray-800">{{ $m->title }}</div>
                        @if($m->rationale)<div class="text-[11px] text-gray-500 mt-0.5">{{ $m->rationale }}</div>@endif
                    </div>
                    <div class="shrink-0 flex items-center gap-1.5">
                        <button wire:click="acceptMeasure({{ $m->id }})" class="text-[12px] font-medium px-2.5 py-1 rounded-md bg-gray-900 text-white hover:bg-gray-700">annehmen</button>
                        <button x-on:click="rej = ! rej" class="text-[12px] px-2.5 py-1 rounded-md border border-gray-200 text-gray-500 hover:text-gray-800">ablehnen</button>
                    </div>
                </div>
                <div x-show="rej" style="display:none" class="mt-2 pt-2 border-t border-gray-100 flex items-center gap-2">
                    <input type="text" x-model="reason" placeholder="Grund (bleibt als Kontext — wird nicht neu vorgeschlagen)" class="flex-1 min-w-0 text-[12px] border border-gray-300 rounded px-2 py-1" />
                    <button x-on:click="$wire.rejectMeasure({{ $m->id }}, reason); rej = false" class="text-[12px] px-2.5 py-1 rounded-md bg-rose-600 text-white shrink-0">ablehnen</button>
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- ANGENOMMEN — Prioritäts-Queue (wartet aufs Tages-Ventil) --}}
@php($accepted = $measures->where('status', 'accepted'))
@if($accepted->isNotEmpty())
    <div class="mt-6">
        <h3 class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-2">Queue · angenommen ({{ $accepted->count() }})</h3>
        <p class="text-[11px] text-gray-400 mb-2">Wartet aufs Tages-Ventil (max. {{ (int) config('seo.measure_daily_cap', 3) }}/Tag nach Flynk) — Flynk-Verdrahtung folgt.</p>
        <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-50">
            @foreach($accepted as $m)
                <div class="flex items-center justify-between gap-3 px-3 py-2 text-[12px]">
                    <span class="text-gray-700 truncate">{{ $m->title }}</span>
                    <span class="shrink-0 text-[10px] text-gray-400">{{ $m->typeLabel() }} · Wert {{ number_format($m->score) }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

{{-- ABGELEHNT — Kontext-Historie (eingeklappt) --}}
@php($rejected = $measures->where('status', 'rejected'))
@if($rejected->isNotEmpty())
    <div x-data="{ open: false }" class="mt-6">
        <button x-on:click="open = ! open" class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 hover:text-gray-600" x-text="(open ? '▾ ' : '▸ ') + 'Abgelehnt ({{ $rejected->count() }}) — Wirkungsraum-Kontext'"></button>
        <div x-show="open" style="display:none" class="mt-2 bg-gray-50/60 rounded-lg border border-gray-100 divide-y divide-gray-100">
            @foreach($rejected as $m)
                <div class="px-3 py-2 text-[12px]">
                    <span class="text-gray-500 line-through">{{ $m->title }}</span>
                    @if($m->reject_reason)<span class="text-[11px] text-gray-400"> · „{{ $m->reject_reason }}"</span>@endif
                </div>
            @endforeach
        </div>
    </div>
@endif
