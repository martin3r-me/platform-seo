{{-- Meta-Station — der Steckbrief des Wirkungsraums: Identität, Ziel/Auftrag
     (bearbeitbar) + Kennzahlen. Steht im roten Faden vor Daten. NX-Sprache.
     Erwartet: $portfolio, $agg, $health, $penetration, $metaEditing. --}}

{{-- Faden-Verortung --}}
<div class="mb-4 flex items-center gap-1.5 flex-wrap text-[10px] text-[color:var(--nx-faint)]">
    <span>Roter Faden:</span>
    <span class="px-1.5 py-0.5 rounded bg-[color:color-mix(in_srgb,var(--nx-info)_12%,transparent)] text-[color:var(--nx-info)] font-medium">Meta · Steckbrief</span>
    <span aria-hidden="true">→</span>
    <button wire:click="setView('measure')" class="px-1.5 py-0.5 rounded bg-[color:var(--nx-line)] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Daten</button>
    <span class="text-[color:var(--nx-faint)]">— zuerst klären, worum es hier geht.</span>
</div>

{{-- Ziel + Auftrag: das Herz des Steckbriefs, bearbeitbar --}}
<x-nx-card class="mb-4">
    <div class="flex items-start justify-between gap-4">
        <div class="text-[10px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Ziel &amp; Auftrag</div>
        @unless($metaEditing)
            <button wire:click="editMeta" class="shrink-0 text-[12px] font-medium text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Bearbeiten</button>
        @endunless
    </div>

    @if($metaEditing)
        <div class="mt-3 space-y-3">
            <div>
                <label class="block text-[11px] font-medium text-[color:var(--nx-muted)] mb-1">Ziel <span class="text-[color:var(--nx-faint)] font-normal">— der Nordstern in einem Satz</span></label>
                <input type="text" wire:model="metaGoal" placeholder="z. B. Maximale gemeinsame Sichtbarkeit des BHG-Verbunds"
                       class="w-full text-[13px] rounded-md px-3 py-2 bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] text-[color:var(--nx-text)] focus:outline-none focus:border-[color:var(--nx-info)]" />
            </div>
            <div>
                <label class="block text-[11px] font-medium text-[color:var(--nx-muted)] mb-1">Auftrag <span class="text-[color:var(--nx-faint)] font-normal">— was dieser Wirkungsraum konkret leisten soll</span></label>
                <textarea wire:model="metaDescription" rows="3" placeholder="z. B. Themen sauber auf die Firmen verteilen, keine Kannibalisierung, gezieltes Cross-Linking …"
                          class="w-full text-[13px] rounded-md px-3 py-2 bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] text-[color:var(--nx-text)] focus:outline-none focus:border-[color:var(--nx-info)] leading-relaxed"></textarea>
            </div>
            <div class="flex items-center gap-2">
                <x-nx-button size="sm" wire:click="saveMeta">Speichern</x-nx-button>
                <button wire:click="cancelMeta" class="text-[12px] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Abbrechen</button>
            </div>
        </div>
    @else
        <div class="mt-2">
            @if($portfolio->goal)
                <p class="text-[15px] font-semibold text-[color:var(--nx-text)] leading-snug">🎯 {{ $portfolio->goal }}</p>
            @else
                <p class="text-[13px] text-[color:var(--nx-faint)] italic">Noch kein Ziel gesetzt — „Bearbeiten", um den Nordstern dieses Wirkungsraums festzuhalten.</p>
            @endif
            @if($portfolio->description)
                <p class="text-[13px] text-[color:var(--nx-muted)] mt-2 leading-relaxed max-w-2xl">{{ $portfolio->description }}</p>
            @endif
        </div>
    @endif
</x-nx-card>

{{-- Kennzahlen: der Umriss des Wirkungsraums auf einen Blick --}}
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
    <x-nx-stat label="URLs" :value="number_format($agg['urls'])" icon="heroicon-o-globe-alt" />
    <x-nx-stat label="Sichtbarkeit" :value="number_format($agg['visibility'])" icon="heroicon-o-eye" />
    <x-nx-stat label="Keywords" :value="number_format($agg['keywords'])" />
    <x-nx-stat label="Suchvolumen" :value="number_format($agg['search_volume'])" />
    <x-nx-stat label="Cluster" :value="number_format(count($penetration['clusters'] ?? []))" hint="Themen" />
    <x-nx-stat label="Phase" :value="$health['current_label'] ?? '—'" hint="Reifegrad" />
</div>

{{-- Was ist ein Wirkungsraum — die Erklärung wohnt jetzt hier (statt als Banner auf jeder Station) --}}
<x-nx-callout title="Was ist ein Wirkungsraum?">
    Die Zentrale: du steuerst eine Menge von URLs (eigene wie fremde) auf <span class="font-medium">ein Ziel</span>. Der rote Faden läuft über die Stationen links — <span class="font-medium">Meta → Daten → Ordnen → Verteilen → Maßnahmen → Wirkung</span>. Jede Station zeigt nur ihr Werkzeug; das Meiste passiert im Posteingang (Maßnahmen). Hier, in Meta, hältst du fest, worum es geht.
</x-nx-callout>
