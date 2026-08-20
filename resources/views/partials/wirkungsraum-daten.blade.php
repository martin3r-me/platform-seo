{{-- Daten-Station — Matrix URL x Quelle in NX-Sprache: Status je Zelle +
     Inline-Aktivierung + Monatskosten. Selbst-erklaerend + Faden-Verortung.
     Erwartet: $members, $availableProfiles, $openDataUrlId, $dataGscProperty,
     $dataPlausibleSiteId. Durchgaengig Block-Direktiven. --}}
@php
    $costSvc = app(\Platform\Seo\Services\SeoCostProjectionService::class);
    $profileSvc = app(\Platform\Seo\Services\SeoDataProfileService::class);

    // Zell-Status: satter Kontrast, damit die Matrix in einer Sekunde lesbar ist.
    $pill = function (array $info, bool $enabled) {
        $last = $info['last'] ?? null;
        $status = $info['status'] ?? null;
        if ($last) {
            $color = $status === 'overdue' ? 'var(--nx-danger)' : ($status === 'due_soon' ? 'var(--nx-warning)' : 'var(--nx-success)');
            return ['sym' => '✓', 'color' => $color, 'text' => 'Daten · ' . $last->diffForHumans(['short' => true]), 'strong' => true];
        }
        if (! $enabled) {
            return ['sym' => '○', 'color' => 'var(--nx-faint)', 'text' => 'aus', 'strong' => false];
        }
        return ['sym' => '⏳', 'color' => 'var(--nx-warning)', 'text' => 'aktiv, noch keine Daten', 'strong' => false];
    };

    $centsById = [];
    $totalCents = 0;
    $gscOn = 0;
    $plaOn = 0;
    foreach ($members as $mU) {
        $c = (int) $costSvc->urlMonthlyCents($mU);
        $centsById[$mU->id] = $c;
        $totalCents += $c;
        if ($mU->gsc_enabled) { $gscOn++; }
        if ($mU->plausible_enabled) { $plaOn++; }
    }
    $n = $members->count();
@endphp

{{-- Intro: was + Faden-Verortung + Legende (selbst-stehend) --}}
<div class="mb-3 max-w-2xl">
    <div class="flex items-center gap-1.5 flex-wrap text-[10px] text-[color:var(--nx-faint)]">
        <span>Roter Faden:</span>
        <button wire:click="setView('meta')" class="px-1.5 py-0.5 rounded bg-[color:var(--nx-line)] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Meta</button>
        <span aria-hidden="true">→</span>
        <span class="px-1.5 py-0.5 rounded bg-[color:color-mix(in_srgb,var(--nx-info)_12%,transparent)] text-[color:var(--nx-info)] font-medium">Daten · was gemessen wird</span>
        <span aria-hidden="true">→</span>
        <button wire:click="setView('ordnen')" class="px-1.5 py-0.5 rounded bg-[color:var(--nx-line)] text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]">Ordnen</button>
        <span class="text-[color:var(--nx-faint)]">— alles fließt aus diesen Daten.</span>
    </div>
    <p class="text-[12px] text-[color:var(--nx-muted)] mt-2 leading-relaxed">Hier steuerst du, <span class="font-medium text-[color:var(--nx-text)]">was</span> je URL gesammelt wird und <span class="font-medium text-[color:var(--nx-text)]">was es kostet</span>. <span class="font-medium text-[color:var(--nx-text)]">GSC/Plausible</span>: Zelle klicken = an/aus, „Einstellen" für site-id/Property. <span class="font-medium text-[color:var(--nx-text)]">Rankings/On-Page/Backlinks</span>: über das Profil. Zellen: <span class="font-semibold" style="color:var(--nx-success)">✓</span> Daten (Alter) · <span style="color:var(--nx-warning)">⏳</span> aktiv, noch keine Daten · <span style="color:var(--nx-faint)">○</span> aus.</p>
</div>

@if($members->isEmpty())
    <x-nx-empty icon="heroicon-o-globe-alt">Noch keine Mitglieds-URLs — oben „+ URLs hinzufügen".</x-nx-empty>
@else
    {{-- Kosten-Summe + Nudge --}}
    <div class="mb-2 flex items-center justify-between gap-4 flex-wrap text-[12px]">
        <div class="text-[color:var(--nx-muted)]">Sammelkosten gesamt: <span class="font-semibold tabular-nums text-[color:var(--nx-text)]">{{ number_format($totalCents / 100, 2, ',', '.') }} €</span> / Monat <span class="text-[color:var(--nx-faint)]">· {{ $n }} URLs</span></div>
        <div class="text-[color:var(--nx-faint)]">GSC aktiv {{ $gscOn }}/{{ $n }} · Plausible {{ $plaOn }}/{{ $n }}@if($gscOn < $n || $plaOn < $n) <span class="text-[color:var(--nx-warning)]">— fehlende Quellen je URL unter „Einstellen" aktivieren</span>@endif</div>
    </div>

    <x-nx-card flush>
        <x-nx-table>
            <x-nx-table-header>
                <x-nx-table-header-cell>URL</x-nx-table-header-cell>
                <x-nx-table-header-cell align="center">Rankings</x-nx-table-header-cell>
                <x-nx-table-header-cell align="center">On-Page</x-nx-table-header-cell>
                <x-nx-table-header-cell align="center">Backlinks</x-nx-table-header-cell>
                <x-nx-table-header-cell align="center">GSC</x-nx-table-header-cell>
                <x-nx-table-header-cell align="center">Plausible</x-nx-table-header-cell>
                <x-nx-table-header-cell align="right">€/Mon</x-nx-table-header-cell>
                <x-nx-table-header-cell align="right"></x-nx-table-header-cell>
            </x-nx-table-header>
            <x-nx-table-body>
                @foreach($members as $u)
                    @php
                        $fresh = method_exists($u, 'collectorFreshness') ? $u->collectorFreshness() : [];
                        $profileActive = $u->data_profile !== null && $u->data_profile !== 'off';
                        $rank = $pill($fresh['serp_ranking'] ?? [], $profileActive);
                        $onp = $pill($fresh['on_page'] ?? [], $profileActive);
                        $back = $pill($fresh['backlinks'] ?? [], $profileActive);
                        $gscP = $pill($fresh['gsc'] ?? [], (bool) $u->gsc_enabled);
                        $plaP = $pill($fresh['plausible'] ?? [], (bool) $u->plausible_enabled);
                        $eff = $profileSvc->effectiveProfile($u);
                        $isOpen = $openDataUrlId === $u->id;
                    @endphp
                    <x-nx-table-row wire:key="data-{{ $u->id }}">
                        <x-nx-table-cell>
                            <a href="{{ route('seo.urls.show', $u->id) }}" wire:navigate class="block truncate max-w-[260px] text-[color:var(--nx-text)] hover:underline">{{ $u->display_label }}</a>
                        </x-nx-table-cell>
                        <x-nx-table-cell align="center"><span class="{{ $rank['strong'] ? 'font-semibold' : '' }}" style="color:{{ $rank['color'] }}" title="Rankings · {{ $rank['text'] }}">{{ $rank['sym'] }}</span></x-nx-table-cell>
                        <x-nx-table-cell align="center"><span class="{{ $onp['strong'] ? 'font-semibold' : '' }}" style="color:{{ $onp['color'] }}" title="On-Page · {{ $onp['text'] }}">{{ $onp['sym'] }}</span></x-nx-table-cell>
                        <x-nx-table-cell align="center"><span class="{{ $back['strong'] ? 'font-semibold' : '' }}" style="color:{{ $back['color'] }}" title="Backlinks · {{ $back['text'] }}">{{ $back['sym'] }}</span></x-nx-table-cell>
                        <x-nx-table-cell align="center"><button wire:click="toggleUrlGsc({{ $u->id }})" class="hover:opacity-60 {{ $gscP['strong'] ? 'font-semibold' : '' }}" style="color:{{ $gscP['color'] }}" title="GSC {{ $u->gsc_enabled ? 'aktiv' : 'aus' }} · {{ $gscP['text'] }} — klick zum Umschalten">{{ $gscP['sym'] }}</button></x-nx-table-cell>
                        <x-nx-table-cell align="center"><button wire:click="toggleUrlPlausible({{ $u->id }})" class="hover:opacity-60 {{ $plaP['strong'] ? 'font-semibold' : '' }}" style="color:{{ $plaP['color'] }}" title="Plausible {{ $u->plausible_enabled ? 'aktiv' : 'aus' }} · {{ $plaP['text'] }} — klick zum Umschalten">{{ $plaP['sym'] }}</button></x-nx-table-cell>
                        <x-nx-table-cell align="right"><span class="tabular-nums text-[color:var(--nx-muted)]">{{ number_format(($centsById[$u->id] ?? 0) / 100, 2, ',', '.') }}</span></x-nx-table-cell>
                        <x-nx-table-cell align="right">
                            <button wire:click="toggleDataSettings({{ $u->id }})" class="text-[12px] font-medium {{ $isOpen ? 'text-[color:var(--nx-text)]' : 'text-[color:var(--nx-muted)] hover:text-[color:var(--nx-text)]' }}">{{ $isOpen ? 'schließen ▴' : 'Einstellen' }}</button>
                        </x-nx-table-cell>
                    </x-nx-table-row>
                    @if($isOpen)
                        <x-nx-table-row wire:key="data-settings-{{ $u->id }}">
                            <x-nx-table-cell colspan="8">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-5 py-1">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <span class="text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">GSC</span>
                                            <button wire:click="toggleUrlGsc({{ $u->id }})" class="text-[11px] px-2 py-0.5 rounded {{ $u->gsc_enabled ? 'text-[color:var(--nx-bg)]' : 'text-[color:var(--nx-muted)]' }}" style="background:{{ $u->gsc_enabled ? 'var(--nx-info)' : 'var(--nx-line)' }}">{{ $u->gsc_enabled ? 'aktiv' : 'aus' }}</button>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" wire:model="dataGscProperty" placeholder="Property (leer = Domain)" class="flex-1 min-w-0 text-[12px] rounded-md px-2 py-1.5 bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] text-[color:var(--nx-text)] focus:outline-none focus:border-[color:var(--nx-info)]" />
                                            <x-nx-button size="sm" wire:click="saveDataGsc">OK</x-nx-button>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <span class="text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)]">Plausible</span>
                                            <button wire:click="toggleUrlPlausible({{ $u->id }})" class="text-[11px] px-2 py-0.5 rounded {{ $u->plausible_enabled ? 'text-[color:var(--nx-bg)]' : 'text-[color:var(--nx-muted)]' }}" style="background:{{ $u->plausible_enabled ? 'var(--nx-info)' : 'var(--nx-line)' }}">{{ $u->plausible_enabled ? 'aktiv' : 'aus' }}</button>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" wire:model="dataPlausibleSiteId" placeholder="site-id (leer = Domain)" class="flex-1 min-w-0 text-[12px] rounded-md px-2 py-1.5 bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] text-[color:var(--nx-text)] focus:outline-none focus:border-[color:var(--nx-info)]" />
                                            <x-nx-button size="sm" wire:click="saveDataPlausible">OK</x-nx-button>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-medium uppercase tracking-wide text-[color:var(--nx-faint)] mb-1.5">Profil <span class="normal-case font-normal">(Tiefe &amp; Kosten)</span></div>
                                        <select x-on:change="$wire.setUrlProfile({{ $u->id }}, $event.target.value)" class="w-full text-[12px] rounded-md px-2 py-1.5 bg-[color:var(--nx-bg)] border border-[color:var(--nx-line)] text-[color:var(--nx-text)] focus:outline-none focus:border-[color:var(--nx-info)]">
                                            @foreach($availableProfiles as $p)
                                                <option value="{{ $p }}" @selected($eff === $p)>{{ ucfirst($p) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </x-nx-table-cell>
                        </x-nx-table-row>
                    @endif
                @endforeach
            </x-nx-table-body>
        </x-nx-table>
    </x-nx-card>
@endif
