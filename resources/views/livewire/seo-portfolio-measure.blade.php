<x-ui-page>
    <x-slot name="navbar">
        <x-ui-page-navbar :title="$portfolio->name" icon="heroicon-o-rocket-launch" />
    </x-slot>

    <x-slot name="actionbar">
        <x-ui-page-actionbar :breadcrumbs="[
            ['label' => 'SEO', 'icon' => 'magnifying-glass-circle', 'route' => 'seo.dashboard'],
            ['label' => 'Wirkungsräume', 'route' => 'seo.portfolios'],
            ['label' => $portfolio->name, 'href' => route('seo.portfolios.show', $portfolio)],
            ['label' => 'Daten'],
        ]" />
    </x-slot>

    <x-slot name="sidebar">
        <livewire:seo.sidebar />
    </x-slot>

    @include('seo::partials.wirkungsraum-sidebar', ['portfolio' => $portfolio, 'active' => 'measure', 'health' => $health])

    <x-ui-page-container>
        <div class="mb-4">
            <h1 class="text-lg font-semibold text-[color:var(--nx-text)]">Daten</h1>
            <p class="text-[10px] text-[color:var(--nx-faint)] mt-0.5">{{ $portfolio->name }} · welche Quellen je URL erhoben werden (Aktivierung + Kosten).</p>
        </div>

        {{-- Daten-Station: Matrix URL × Quelle (Aktivierung + Kosten) --}}
        @include('seo::partials.wirkungsraum-daten', ['members' => $members, 'availableProfiles' => $availableProfiles, 'openDataUrlId' => $openDataUrlId, 'dataGscProperty' => $dataGscProperty, 'dataPlausibleSiteId' => $dataPlausibleSiteId])

        {{-- Datenquellen-Einstellungen je URL im NX-Modal (Standard-Formular). --}}
        <x-ui-modal wire:model="showDataSettings" title="Datenquellen{{ $dataUrlLabel ? ' — '.$dataUrlLabel : '' }}">
            <form wire:submit="saveDataSettings">
                <div class="space-y-4">
                    <p class="text-sm text-gray-500">Was für diese URL gesammelt wird. <span class="font-medium text-gray-700">GSC/Plausible</span> sind gratis, das <span class="font-medium text-gray-700">Profil</span> steuert Tiefe &amp; Kosten der bezahlten Quellen.</p>

                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1">
                            <input type="checkbox" wire:model="dataGscEnabled" class="rounded">
                            <span>Google Search Console</span>
                        </label>
                        <input type="text" wire:model.blur="dataGscProperty" placeholder="Property (leer = Domain)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>

                    <div>
                        <label class="flex items-center gap-2 text-sm font-medium text-gray-700 mb-1">
                            <input type="checkbox" wire:model="dataPlausibleEnabled" class="rounded">
                            <span>Plausible</span>
                        </label>
                        <input type="text" wire:model.blur="dataPlausibleSiteId" placeholder="site-id (leer = Domain)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Profil <span class="text-gray-400 font-normal">— Rankings / On-Page / Backlinks (Tiefe &amp; Kosten)</span></label>
                        <select wire:model="dataProfile" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                            @foreach($availableProfiles as $p)
                                <option value="{{ $p }}">{{ ucfirst($p) }}</option>
                            @endforeach
                        </select>
                        @if($dataSettingsUrl)
                            <p class="text-xs text-gray-500 mt-1">Monatskosten dieser URL: <span class="font-medium text-gray-600 tabular-nums">{{ number_format(app(\Platform\Seo\Services\SeoCostProjectionService::class)->urlMonthlyCents($dataSettingsUrl) / 100, 2, ',', '.') }} €</span> <span class="text-gray-400">(nach Speichern aktualisiert)</span></p>
                        @endif
                    </div>
                </div>
                <x-slot name="footer">
                    <x-ui-button variant="secondary" size="sm" wire:click="closeDataSettings" type="button">Abbrechen</x-ui-button>
                    <x-ui-button variant="primary" size="sm" wire:click="saveDataSettings" type="button">Speichern</x-ui-button>
                </x-slot>
            </form>
        </x-ui-modal>
    </x-ui-page-container>
</x-ui-page>
