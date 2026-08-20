<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$portfolio->name" icon="heroicon-o-rocket-launch" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wirkungsräume', 'route' => 'seo.portfolios'],
            ['label' => $portfolio->name, 'href' => route('seo.portfolios.show', $portfolio)],
            ['label' => 'Posteingang'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    @include('seo::partials.wirkungsraum-sidebar', ['portfolio' => $portfolio, 'active' => 'inbox', 'health' => $health])

    <x-ui-page-container>
        <div class="max-w-3xl">
            {{-- Kopf --}}
            <div class="flex items-start justify-between gap-4 mb-1">
                <div class="min-w-0">
                    <h1 class="text-lg font-semibold text-[color:var(--nx-text)]">Posteingang</h1>
                    <div class="text-[10px] text-[color:var(--nx-faint)] mt-0.5 flex items-center gap-1.5 flex-wrap">
                        <span>{{ $portfolio->name }}</span>
                        <span aria-hidden="true">·</span>
                        <span>die Zentrale des Wirkungsraums — hier wird entschieden, was in Produktion geht</span>
                    </div>
                </div>
                <button wire:click="generateMeasures" wire:loading.attr="disabled" wire:target="generateMeasures"
                        class="shrink-0 text-[12px] font-medium px-3 py-1.5 rounded-md border border-[color:var(--nx-line)] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)] hover:bg-[color:var(--nx-line)]">
                    <span wire:loading.remove wire:target="generateMeasures">↻ Vorschläge holen</span>
                    <span wire:loading wire:target="generateMeasures">prüfe…</span>
                </button>
            </div>

            <p class="text-[12px] text-[color:var(--nx-muted)] mb-4 max-w-2xl leading-relaxed">Vorgeschlagene Maßnahmen <span class="font-medium text-[color:var(--nx-text)]">annehmen</span> (→ Queue → Flynk) oder <span class="font-medium text-[color:var(--nx-text)]">begründet ablehnen</span> (bleibt als Wirkungsraum-Kontext, wird nicht neu vorgeschlagen). Nach Wert sortiert. Gespeist aus den Signalen (Kannibalisierung, Pillar-Kandidaten, veraltet/GEO-Lücke) + KI.</p>

            @if($measureFlash)
                <p class="text-[11px] mb-3" style="color:var(--nx-info)">{{ $measureFlash }}</p>
            @endif

            {{-- NEU — die eigentliche Triage --}}
            @php($proposed = $measures->where('status', 'proposed'))
            @if($proposed->isEmpty())
                <x-nx-card class="border-dashed">
                    <p class="text-[12px] text-[color:var(--nx-muted)]">Posteingang leer. „↻ Vorschläge holen" rechnet Nachfrage/Signale/KI neu und legt neue Maßnahmen hier ab.</p>
                </x-nx-card>
            @else
                <div class="space-y-2">
                    @foreach($proposed as $m)
                        @php($routeColor = ['flynk' => 'var(--nx-info)', 'internal' => 'var(--nx-muted)', 'human' => 'var(--nx-warning)'][$m->route] ?? 'var(--nx-muted)')
                        <div wire:key="measure-{{ $m->id }}" x-data="{ rej: false, reason: '' }">
                            <x-nx-card>
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5 flex-wrap mb-1">
                                            <span class="text-[9px] uppercase tracking-wide px-1.5 py-0.5 rounded" style="background:color-mix(in srgb, {{ $routeColor }} 12%, transparent);color:{{ $routeColor }}">{{ $m->typeLabel() }}</span>
                                            @if($m->source === 'ai')<span class="text-[9px] uppercase tracking-wide px-1 py-0.5 rounded" style="background:color-mix(in srgb, var(--nx-info) 12%, transparent);color:var(--nx-info)" title="KI-Vorschlag">🤖 KI</span>@endif
                                            <span class="text-[9px] uppercase tracking-wide text-[color:var(--nx-faint)]">→ {{ $m->route }}</span>
                                            @if($m->score > 0)<span class="text-[10px] text-[color:var(--nx-faint)] tabular-nums">Wert {{ number_format($m->score) }}</span>@endif
                                        </div>
                                        <div class="text-[13px] font-medium text-[color:var(--nx-text)]">{{ $m->title }}</div>
                                        @if($m->targetUrl)
                                            <div class="text-[10px] text-[color:var(--nx-faint)] mt-0.5">📍 {{ $m->targetUrl->display_label }}</div>
                                        @elseif($m->targetCluster)
                                            <div class="text-[10px] text-[color:var(--nx-faint)] mt-0.5">📍 Cluster „{{ $m->targetCluster->name }}"</div>
                                        @endif
                                        @if($m->rationale)<div class="text-[11px] text-[color:var(--nx-muted)] mt-0.5"><span class="text-[color:var(--nx-faint)]">Warum:</span> {{ $m->rationale }}</div>@endif
                                        @if($m->expected_result)<div class="text-[11px] mt-0.5" style="color:var(--nx-success)"><span class="opacity-70">Erwartet:</span> {{ $m->expected_result }}</div>@endif
                                    </div>
                                    <div class="shrink-0 flex items-center gap-1.5">
                                        <x-nx-button size="sm" wire:click="acceptMeasure({{ $m->id }})">annehmen</x-nx-button>
                                        <button x-on:click="rej = ! rej" class="text-[12px] px-2.5 py-1 rounded-md border border-[color:var(--nx-line)] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">ablehnen</button>
                                    </div>
                                </div>
                                <div x-show="rej" style="display:none" class="mt-2 pt-2 border-t border-[color:var(--nx-line)] flex items-center gap-2">
                                    <input type="text" x-model="reason" placeholder="Grund (bleibt als Kontext — wird nicht neu vorgeschlagen)" class="flex-1 min-w-0 text-[12px] border border-[color:var(--nx-line)] rounded px-2 py-1 bg-[color:var(--nx-bg)] text-[color:var(--nx-text)]" />
                                    <button x-on:click="$wire.rejectMeasure({{ $m->id }}, reason); rej = false" class="text-[12px] px-2.5 py-1 rounded-md text-white shrink-0" style="background:var(--nx-danger)">ablehnen</button>
                                </div>
                            </x-nx-card>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- ANGENOMMEN — Prioritäts-Queue --}}
            @php($accepted = $measures->where('status', 'accepted'))
            @if($accepted->isNotEmpty())
                <div class="mt-6">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-[color:var(--nx-muted)] mb-2">Queue · angenommen ({{ $accepted->count() }})</h3>
                    <p class="text-[11px] text-[color:var(--nx-faint)] mb-2">Wartet aufs Tages-Ventil (max. {{ (int) config('seo.measure_daily_cap', 3) }}/Tag nach Flynk) — Flynk-Verdrahtung folgt.</p>
                    <x-nx-card flush>
                        <div class="divide-y divide-[color:var(--nx-line)]">
                            @foreach($accepted as $m)
                                <div class="flex items-center justify-between gap-3 px-3 py-2 text-[12px]">
                                    <span class="text-[color:var(--nx-text)] truncate">{{ $m->title }}</span>
                                    <span class="shrink-0 text-[10px] text-[color:var(--nx-faint)]">{{ $m->typeLabel() }} · Wert {{ number_format($m->score) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-nx-card>
                </div>
            @endif

            {{-- ABGELEHNT — Kontext-Historie (eingeklappt) --}}
            @php($rejected = $measures->where('status', 'rejected'))
            @if($rejected->isNotEmpty())
                <div x-data="{ open: false }" class="mt-6">
                    <button x-on:click="open = ! open" class="text-[11px] font-semibold uppercase tracking-wide text-[color:var(--nx-faint)] hover:text-[color:var(--nx-muted)]" x-text="(open ? '▾ ' : '▸ ') + 'Abgelehnt ({{ $rejected->count() }}) — Wirkungsraum-Kontext'"></button>
                    <div x-show="open" style="display:none" class="mt-2 rounded-lg border border-[color:var(--nx-line)] divide-y divide-[color:var(--nx-line)]">
                        @foreach($rejected as $m)
                            <div class="px-3 py-2 text-[12px]">
                                <span class="text-[color:var(--nx-muted)] line-through">{{ $m->title }}</span>
                                @if($m->reject_reason)<span class="text-[11px] text-[color:var(--nx-faint)]"> · „{{ $m->reject_reason }}"</span>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </x-ui-page-container>
</x-ui-page>
