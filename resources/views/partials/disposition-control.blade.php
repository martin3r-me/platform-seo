{{-- Zurückbauen-Kontrolle (De-Invest der Angebots-Achse). Erwartet: $urlId, $disposition. --}}
<span x-data="{ open: false }" class="relative inline-block">
    @if(! empty($disposition))
        <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:{{ $disposition === 'retire' ? '#fee2e2' : ($disposition === 'rebuild' ? '#fef3c7' : '#e0e7ff') }};color:{{ $disposition === 'retire' ? '#b91c1c' : ($disposition === 'rebuild' ? '#b45309' : '#4338ca') }}">{{ ['retire' => 'abschaffen', 'rebuild' => 'umbauen', 'retarget' => 're-target'][$disposition] ?? $disposition }}</span>
        <button wire:click="clearDisposition({{ $urlId }})" class="text-[10px] text-gray-400 hover:text-gray-700 ml-0.5" title="Markierung aufheben">×</button>
    @else
        <button type="button" x-on:click="open = ! open" class="text-[11px] text-gray-500 hover:text-rose-600 whitespace-nowrap">zurückbauen ▾</button>
        <div x-show="open" x-on:click.outside="open = false" style="display:none" class="absolute right-0 top-full z-20 mt-1 bg-white border border-gray-200 rounded-lg shadow-lg py-1 min-w-[140px] text-left">
            <button wire:click="setDisposition({{ $urlId }}, 'retire')" x-on:click="open = false" class="block w-full text-left px-3 py-1 text-[12px] text-gray-700 hover:bg-gray-50" title="Seite raus / Redirect">abschaffen</button>
            <button wire:click="setDisposition({{ $urlId }}, 'rebuild')" x-on:click="open = false" class="block w-full text-left px-3 py-1 text-[12px] text-gray-700 hover:bg-gray-50" title="Seite neu aufsetzen">umbauen</button>
            <button wire:click="setDisposition({{ $urlId }}, 'retarget')" x-on:click="open = false" class="block w-full text-left px-3 py-1 text-[12px] text-gray-700 hover:bg-gray-50" title="auf anderes Thema/KW setzen">re-targeten</button>
        </div>
    @endif
</span>
