{{-- Daten-Station — Matrix URL × Quelle. Status je Zelle + Inline-Aktivierung
     (GSC/Plausible: an/aus + site-id/property; Rankings/On-Page/Backlinks: Profil)
     + Monatskosten. Erwartet: $members, $availableProfiles.
     Durchgängig Block-Direktiven (keine inline-Variante in dieser Datei). --}}
@php
    $costSvc = app(\Platform\Seo\Services\SeoCostProjectionService::class);
    $profileSvc = app(\Platform\Seo\Services\SeoDataProfileService::class);

    // Status-Pille je Quelle/URL aus collectorFreshness():
    //  ✓ Daten vorhanden (Farbe nach Aktualität) · ⏳ aktiv, noch keine Daten · ○ aus.
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
@endphp

<div class="mb-3">
    <h2 class="text-[13px] font-semibold text-gray-700">Daten je URL</h2>
    <p class="text-[11px] text-gray-400 mt-0.5">Welche URL hat welche Quellen — pro Quelle aktivieren. GSC/Plausible: Zelle klicken = an/aus, „⚙ Settings" für site-id/Property. Rankings/On-Page/Backlinks: Profil (Tiefe &amp; Kosten).</p>
</div>

@if($members->isEmpty())
    <div class="bg-white rounded-lg border border-gray-200 p-6 text-[12px] text-gray-500">Noch keine Mitglieds-URLs — oben „+ URLs hinzufügen".</div>
@else
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
                        $cents = $costSvc->urlMonthlyCents($u);
                        $eff = $profileSvc->effectiveProfile($u);
                    @endphp
                    <tr x-data="{ open: false, prop: @js($u->gsc_property ?? ''), site: @js($u->plausible_site_id ?? '') }" class="border-b border-gray-50 last:border-0">
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
                        <td class="px-3 py-2 text-right tabular-nums text-gray-500">{{ number_format($cents / 100, 2, ',', '.') }}</td>
                        <td class="px-2 py-2 text-right whitespace-nowrap">
                            <button x-on:click="open = ! open" class="text-[11px] text-gray-400 hover:text-gray-700" x-text="open ? 'schließen' : '⚙ Settings'"></button>
                        </td>
                    </tr>
                    <tr x-show="open" style="display:none" class="bg-gray-50/60 border-b border-gray-100">
                        <td colspan="8" class="px-3 py-3">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">GSC</span>
                                        <button wire:click="toggleUrlGsc({{ $u->id }})" class="text-[11px] px-2 py-0.5 rounded {{ $u->gsc_enabled ? 'bg-[#166EE1] text-white' : 'bg-gray-200 text-gray-600' }}">{{ $u->gsc_enabled ? 'aktiv' : 'aus' }}</button>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <input type="text" x-model="prop" placeholder="Property (leer = Domain)" class="flex-1 min-w-0 text-[12px] border border-gray-300 rounded px-2 py-1" />
                                        <button x-on:click="$wire.saveUrlGscProperty({{ $u->id }}, prop)" class="text-[12px] px-2 py-1 rounded bg-gray-900 text-white shrink-0">OK</button>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex items-center justify-between mb-1">
                                        <span class="text-[11px] font-medium text-gray-500 uppercase tracking-wide">Plausible</span>
                                        <button wire:click="toggleUrlPlausible({{ $u->id }})" class="text-[11px] px-2 py-0.5 rounded {{ $u->plausible_enabled ? 'bg-[#166EE1] text-white' : 'bg-gray-200 text-gray-600' }}">{{ $u->plausible_enabled ? 'aktiv' : 'aus' }}</button>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <input type="text" x-model="site" placeholder="site-id (leer = Domain)" class="flex-1 min-w-0 text-[12px] border border-gray-300 rounded px-2 py-1" />
                                        <button x-on:click="$wire.saveUrlPlausibleSiteId({{ $u->id }}, site)" class="text-[12px] px-2 py-1 rounded bg-gray-900 text-white shrink-0">OK</button>
                                    </div>
                                </div>
                                <div>
                                    <div class="text-[11px] font-medium text-gray-500 uppercase tracking-wide mb-1">Profil <span class="text-gray-400 normal-case">(Rankings/On-Page/Backlinks)</span></div>
                                    <select x-on:change="$wire.setUrlProfile({{ $u->id }}, $event.target.value)" class="w-full text-[12px] border border-gray-300 rounded px-2 py-1 bg-white">
                                        @foreach($availableProfiles as $p)
                                            <option value="{{ $p }}" @selected($eff === $p)>{{ ucfirst($p) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-[10px] text-gray-400">✓ Daten vorhanden (Alter) · ⏳ aktiv, noch keine Daten · ○ aus · €/Mon = Sammelkosten je URL nach Profil.</p>
@endif
