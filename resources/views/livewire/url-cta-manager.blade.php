{{-- Ziel-CTAs je URL pflegen. Footer-Aktionen per wire:click (x-ui-modal
     rendert den Footer außerhalb des <form>). Typ ist Pflicht; Zeilen ohne Typ
     fallen beim Speichern raus. --}}
<div>
    <x-ui-modal wire:model="show" title="Ziel-CTAs">
        <div class="space-y-3">
            <p class="text-[11px] text-gray-400 -mt-1">
                Was diese Seite den Besucher tun lassen soll — typisiert. Geht per Flynk-Push in Produktion; die Agentur baut Platzierung, Copy und Design.
                @if($urlLabel !== '')<span class="text-gray-500">· {{ $urlLabel }}</span>@endif
            </p>

            @if($ctaTypes->isEmpty())
                <p class="text-[11px] rounded-md px-3 py-2 bg-amber-50 text-amber-700">Noch keine CTA-Typen angelegt — per MCP <code>seo.cta_types.POST</code> pflegen.</p>
            @endif

            <div class="space-y-2">
                @forelse($ctas as $i => $row)
                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 px-2 py-1.5">
                        <select wire:model="ctas.{{ $i }}.prominence"
                                class="text-[11px] border border-gray-200 rounded px-1.5 py-1 text-gray-600 focus:outline-none focus:border-indigo-400">
                            <option value="primary">primär</option>
                            <option value="secondary">sekundär</option>
                            <option value="tertiary">tertiär</option>
                        </select>
                        <select wire:model="ctas.{{ $i }}.cta_type_id"
                                class="text-[12px] border border-gray-200 rounded px-2 py-1 text-gray-700 focus:outline-none focus:border-indigo-400">
                            <option value="">Typ …</option>
                            @foreach($ctaTypes as $t)
                                <option value="{{ $t->id }}">{{ $t->label }}</option>
                            @endforeach
                        </select>
                        <input type="text" wire:model="ctas.{{ $i }}.label" placeholder="Copy — z. B. „Catering anfragen"
                               class="flex-1 min-w-0 text-[12px] border border-gray-200 rounded px-2 py-1 focus:outline-none focus:border-indigo-400">
                        <input type="text" wire:model="ctas.{{ $i }}.target" placeholder="Ziel — URL / tel:"
                               class="w-36 text-[12px] border border-gray-200 rounded px-2 py-1 focus:outline-none focus:border-indigo-400">
                        <button type="button" wire:click="removeCta({{ $i }})" class="text-gray-300 hover:text-rose-600 text-lg leading-none px-1" title="entfernen">&times;</button>
                    </div>
                @empty
                    <p class="text-[12px] text-gray-400">Noch keine Ziel-CTAs. „+ CTA" hinzufügen.</p>
                @endforelse
            </div>

            <button type="button" wire:click="addCta"
                    class="text-[12px] px-2.5 py-1 rounded border border-gray-200 text-gray-600 hover:border-indigo-400 hover:text-indigo-600">+ CTA</button>
        </div>

        <x-slot name="footer">
            <x-ui-button variant="secondary" size="sm" wire:click="close" type="button">Abbrechen</x-ui-button>
            <x-ui-button variant="primary" size="sm" wire:click="save" type="button">Speichern</x-ui-button>
        </x-slot>
    </x-ui-modal>
</div>
