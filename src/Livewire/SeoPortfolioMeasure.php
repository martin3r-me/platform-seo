<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoDataProfileService;
use Platform\Seo\Services\SeoPortfolioHealth;
use Platform\Seo\Services\SeoPortfolioView;

/**
 * Station „Daten" (measure) als eigene Route/Komponente — die Matrix URL × Quelle
 * (Aktivierung + Kosten) + das Datenquellen-Einstellungs-Modal je URL. Herausgelöst
 * aus der Gott-Komponente; Mitglieder via SeoPortfolioView.
 */
class SeoPortfolioMeasure extends Component
{
    use ResolvesTeamSettings;

    public SeoPortfolio $portfolio;

    /** Daten-Matrix: welche URL im Settings-Modal offen ist + ihre (gestuften) Felder. */
    public bool $showDataSettings = false;

    public ?int $openDataUrlId = null;

    public string $dataUrlLabel = '';

    public bool $dataGscEnabled = false;

    public string $dataGscProperty = '';

    public bool $dataPlausibleEnabled = false;

    public string $dataPlausibleSiteId = '';

    public string $dataProfile = '';

    public function mount(SeoPortfolio $seoPortfolio): void
    {
        $this->resolveSettings();
        abort_unless((int) $seoPortfolio->team_id === (int) $this->seoSettings->team_id, 404);
        $this->portfolio = $seoPortfolio;
    }

    public function openDataSettings(int $urlId): void
    {
        $u = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($urlId);
        if (! $u) {
            return;
        }
        $this->openDataUrlId = $urlId;
        $this->dataUrlLabel = (string) $u->display_label;
        $this->dataGscEnabled = (bool) $u->gsc_enabled;
        $this->dataGscProperty = (string) ($u->gsc_property ?? '');
        $this->dataPlausibleEnabled = (bool) $u->plausible_enabled;
        $this->dataPlausibleSiteId = (string) ($u->plausible_site_id ?? '');
        $this->dataProfile = app(SeoDataProfileService::class)->effectiveProfile($u);
        $this->showDataSettings = true;
    }

    public function closeDataSettings(): void
    {
        $this->showDataSettings = false;
        $this->openDataUrlId = null;
    }

    /** Beim Schließen über Backdrop/X (wire:model) die Auswahl mit aufräumen. */
    public function updatedShowDataSettings(bool $value): void
    {
        if (! $value) {
            $this->openDataUrlId = null;
        }
    }

    /** Alle Datenquellen-Felder der URL in einem Rutsch speichern (Form-Modal). */
    public function saveDataSettings(): void
    {
        if (! $this->openDataUrlId) {
            return;
        }
        $u = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($this->openDataUrlId);
        if (! $u) {
            return;
        }
        $svc = app(SeoDataProfileService::class);
        $attrs = [
            'gsc_enabled' => $this->dataGscEnabled,
            'gsc_property' => trim($this->dataGscProperty) ?: null,
            'plausible_enabled' => $this->dataPlausibleEnabled,
            'plausible_site_id' => trim($this->dataPlausibleSiteId) ?: null,
        ];
        if ($svc->isValidProfile((bool) $u->is_own, $this->dataProfile)) {
            $attrs['data_profile'] = $this->dataProfile;
        }
        $u->update($attrs);
        $this->closeDataSettings();
    }

    public function render()
    {
        $pv = app(SeoPortfolioView::class)->forPortfolio($this->portfolio);

        return view('seo::livewire.seo-portfolio-measure', [
            'portfolio' => $this->portfolio,
            'members' => $pv['members'],
            'health' => app(SeoPortfolioHealth::class)->evaluate($this->portfolio),
            'availableProfiles' => app(SeoDataProfileService::class)->availableProfiles(true),
            'dataSettingsUrl' => ($this->showDataSettings && $this->openDataUrlId)
                ? SeoUrl::where('team_id', $this->seoSettings->team_id)->find($this->openDataUrlId)
                : null,
        ])->layout('platform::layouts.app');
    }
}
