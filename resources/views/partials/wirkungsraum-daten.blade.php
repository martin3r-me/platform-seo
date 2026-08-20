{{-- Daten-Station — Matrix URL x Quelle: Status je Zelle + Inline-Aktivierung +
     Monatskosten. Selbst-erklaerend + Faden-Verortung. Erwartet: $members,
     $availableProfiles, $openDataUrlId, $dataGscProperty, $dataPlausibleSiteId.
     Durchgaengig Block-Direktiven. --}}
@php
    $costSvc = app(\Platform\Seo\Services\SeoCostProjectionService::class);
    $profileSvc = app(\Platform\Seo\Services\SeoDataProfileService::class);

    $pill = function (array $info, bool $enabled) {
        $last = $info['last'] ?? null;
        $status = $info['status'] ?? null;
        if ($last) {
            $color = $status === 'overdue' ? '#dc2626' : ($status === 'due_soon' ? '#d97706' : '#15803d');
            return ['sym' => '✓', 'color' => $color, 'text' => $last->diffForHumans(['short' => true])];
        }
        if (! $enabled) {
            return ['sym' => '○', 'color' => '#9ca3af', 'text' => 'aus'];
        }
        return ['sym' => '⏳', 'color' => '#6b7280', 'text' => 'geplant'];
    };

    // Kosten-Summe + Quell-Abdeckung vorab (fuer die Kosten-/Handlungs-Story).
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
    <h2 class="text-[13px] font-semibold text-gray-700">Daten <span class="text-gray-400 font-normal">· welche Quelle jede URL hat</span></h2>
    <div class="text-[10px] text-gray-400 mt-1 flex items-center gap-1.5 flex-wrap">
        <span>Roter Faden:</span>
        <span class="px-1 py-0.5 rounded bg-indigo-50 text-indigo-600 font-medium">Daten · was gemessen wird</span>
        <span>→</span>
        <button wire:click="setView('ordnen')" class="px-1 py-0.5 rounded bg-gray-100 text-gray-500 hover:text-gray-800 hover:underline">Ordnen</button>
        <span class="text-gray-300">— alles fließt aus diesen Daten.</span>
    </div>
    <p class="text-[11px] text-gray-500 mt-1.5 leading-relaxed">Hier steuerst du, <span class="font-medium">was</span> je URL gesammelt wird und <span class="font-medium">was es kostet</span>. <span class="font-medium">GSC/Plausible</span>: Zelle klicken = an/aus, „⚙ Settings" für site-id/Property. <span class="font-medium">Rankings/On-Page/Backlinks</span>: über das Profil (Tiefe &amp; Kosten). Zellen: <span style="color:#15803d">✓</span> Daten vorhanden (Alter) · <span style="color:#6b7280">⏳</span> aktiv, noch keine Daten · <span style="color:#9ca3af">○</span> aus.</p>
</div>

@if($members->isEmpty())
    <div class="bg-white rounded-lg border border-gray-200 p-6 text-[12px] text-gray-500">Noch keine Mitglieds-URLs — oben „+ URLs hinzufügen".</div>
@else
    {{-- Kosten-Summe + Nudge --}}
    <div class="mb-2 flex items-center justify-between gap-4 flex-wrap text-[11px]">
        <div class="text-gray-600">Sammelkosten gesamt: <span class="font-semibold tabular-nums">{{ number_format($totalCents / 100, 2, ',', '.') }} €</span> / Monat <span class="text-gray-400">· {{ $n }} URLs</span></div>
        <div class="text-gray-400">GSC aktiv {{ $gscOn }}/{{ $n }} · Plausible {{ $plaOn }}/{{ $n }}@if($gscOn < $n || $plaOn < $n) <span style="color:#b45309">— fehlende Quellen je URL über „⚙ Settings" aktivieren</span>@endif</div>
    </div>

    <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
        <table class="w-full text-[12px]" style="min-width:820px">
            <thead>
                <tr class="text-[10px] uppercase tracking-wide text-gray-400 border-b border-gray-100">
                    <th class="text-left font-medium px-3 py-2">URL</th>
                    <th class="text-center font-medium px-2 py-2">Rankings</th>
                    <th class="text-center font-medium px-2 py-2">On-Page</th>
                    <th class="text-center font-medium px-2 py-2">Backlinks</th>
                    <th class="text-center font-medium px-2 py-2">GSC</th>
                    <th class="text-center font-medium px-2 py-2">Plausible</th>
                    <th class="text-right font-medium px-3 py-2">€/Mon</th>
                    <th class="px-2 py-2"></th>
                </tr>
            </thead>
            <tbody>
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
                    <tr wire:key="data-{{ $u->id }}" class="border-b border-gray-50 {{ $isOpen ? '' : 'last:border-0' }}">
                        <td class="px-3 py-2">
                            <a href="{{ route('seo.urls.show', $u->id) }}" wire:navigate class="text-gray-700 hover:underline block truncate" style="max-width:260px">{{ $u->display_label }}</a>
                        </td>
                        <td class="px-2 py-2 text-center" title="Rankings · {{ $rank['text'] }}"><span style="color:{{ $rank['color'] }}">{{ $rank['sym'] }}</span></td>
                        <td class="px-2 py-2 text-center" title="On-Page · {{ $onp['text'] }}"><span style="color:{{ $onp['color'] }}">{{ $onp['sym'] }}</span></td>
                        <td class="px-2 py-2 text-center" title="Backlinks · {{ $back['text'] }}"><span style="color:{{ $back['color'] }}">{{ $back['sym'] }}</span></td>
                        <td class="px-2 py-2 text-center">
                            <button wire:click="toggleUrlGsc({{ $u->id }})" class="hover:opacity-60" title="GSC {{ $u->gsc_enabled ? 'aktiv' : 'aus' }} · {{ $gscP['text'] }} — klick zum Umschalten"><span style="color:{{ $gscP['color'] }}">{{ $gscP['sym'] }}</span></button>
                        </td>
                        <td class="px-2 py-2 text-center">
                            <button wire:click="toggleUrlPlausible({{ $u->id }})" class="hover:opacity-60" title="Plausible {{ $u->plausible_enabled ? 'aktiv' : 'aus' }} · {{ $plaP['text'] }} — klick zum Umschalten"><span style="color:{{ $plaP['color'] }}">{{ $plaP['sym'] }}</span></button>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ number_format(($centsById[$u->id] ?? 0) / 100, 2, ',', '.') }}</td>
                        <td class="px-2 py-2 text-right whitespace-nowrap">
                            <button wire:click="toggleDataSettings({{ $u->id }})" class="text-[11px] {{ $isOpen ? 'text-gray-700' : 'text-gray-400 hover:text-gray-700' }}">{{ $isOpen ? 'schließen ▴' : '⚙ Settings' }}</button>
                        </td>
                    </tr>
                    @if($isOpen)
                        <tr wire:key="data-settings-{{ $u->id }}" class="bg-gray-50/60 border-b border-gray-100">
                            <td colspan="8" class="px-3 py-3">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">GSC</span>
                                            <button wire:click="toggleUrlGsc({{ $u->id }})" class="text-[11px] px-2 py-0.5 rounded {{ $u->gsc_enabled ? 'bg-[#166EE1] text-white' : 'bg-gray-200 text-gray-600' }}">{{ $u->gsc_enabled ? 'aktiv' : 'aus' }}</button>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" wire:model="dataGscProperty" placeholder="Property (leer = Domain)" class="flex-1 min-w-0 text-[12px] border border-gray-300 rounded px-2 py-1" />
                                            <button wire:click="saveDataGsc" class="text-[12px] px-2 py-1 rounded bg-gray-900 text-white shrink-0">OK</button>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Plausible</span>
                                            <button wire:click="toggleUrlPlausible({{ $u->id }})" class="text-[11px] px-2 py-0.5 rounded {{ $u->plausible_enabled ? 'bg-[#166EE1] text-white' : 'bg-gray-200 text-gray-600' }}">{{ $u->plausible_enabled ? 'aktiv' : 'aus' }}</button>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <input type="text" wire:model="dataPlausibleSiteId" placeholder="site-id (leer = Domain)" class="flex-1 min-w-0 text-[12px] border border-gray-300 rounded px-2 py-1" />
                                            <button wire:click="saveDataPlausible" class="text-[12px] px-2 py-1 rounded bg-gray-900 text-white shrink-0">OK</button>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Profil <span class="text-gray-400 normal-case">(Rankings/On-Page/Backlinks · Tiefe &amp; Kosten)</span></div>
                                        <select x-on:change="$wire.setUrlProfile({{ $u->id }}, $event.target.value)" class="w-full text-[12px] border border-gray-300 rounded px-2 py-1 bg-white">
                                            @foreach($availableProfiles as $p)
                                                <option value="{{ $p }}" @selected($eff === $p)>{{ ucfirst($p) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
@endif
