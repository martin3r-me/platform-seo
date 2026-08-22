<?php

namespace Platform\Seo\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoGeoLocation;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlDimension;
use Platform\Seo\Services\SeoBaseClusterBuilder;

/**
 * SEO-Ziel je URL — die Dimensionen (Basis/GEO/Anlass/Typ/Zielgruppe) als
 * einfache Settings. Eigenständige, event-geöffnete Modal-Komponente (wie
 * ClusterModal), kein Anbau an die God-Komponente. Schreibt seo_url_dimensions;
 * GEO wählt aus dem Geo-Katalog (exakter location_code, kein Freitext).
 */
class UrlSeoTarget extends Component
{
    use ResolvesTeamSettings;

    public bool $show = false;

    public ?int $urlId = null;

    public string $urlLabel = '';

    /** Multi-Wert-Dimensionen (Basis/Anlass/Typ/Zielgruppe) → Liste von Werten. */
    public array $values = ['basis' => [], 'anlass' => [], 'typ' => [], 'zielgruppe' => []];

    /** Eingabe-Puffer je Multi-Dimension. */
    public array $buffers = ['basis' => '', 'anlass' => '', 'typ' => '', 'zielgruppe' => ''];

    /** GEO (single) — aus dem Katalog gewählt. */
    public ?int $geoLocationId = null;

    public ?string $geoName = null;

    public string $geoSearch = '';

    /** Ergebnis-/Fehlermeldung der Basis-Cluster-Erzeugung. */
    public ?string $buildResult = null;

    public bool $buildError = false;

    public function mount(): void
    {
        $this->resolveSettings();
    }

    #[On('open-url-target')]
    public function open(int $urlId): void
    {
        $url = SeoUrl::where('team_id', $this->seoSettings->team_id)->where('id', $urlId)->first();
        if (! $url) {
            return;
        }
        $this->urlId = $url->id;
        $this->urlLabel = $url->display_label;
        $this->loadDimensions();
        $this->show = true;
    }

    protected function loadDimensions(): void
    {
        $this->values = ['basis' => [], 'anlass' => [], 'typ' => [], 'zielgruppe' => []];
        $this->buffers = ['basis' => '', 'anlass' => '', 'typ' => '', 'zielgruppe' => ''];
        $this->geoLocationId = null;
        $this->geoName = null;
        $this->geoSearch = '';
        $this->buildResult = null;
        $this->buildError = false;
        $this->resetErrorBag();

        foreach (SeoUrlDimension::where('url_id', $this->urlId)->get() as $dim) {
            if ($dim->dimension === SeoUrlDimension::DIM_GEO) {
                $this->geoLocationId = $dim->geo_location_id;
                $this->geoName = $dim->value;
            } elseif (isset($this->values[$dim->dimension])) {
                $this->values[$dim->dimension][] = $dim->value;
            }
        }
    }

    public function addValue(string $dimension): void
    {
        if (! isset($this->values[$dimension])) {
            return;
        }
        $v = mb_substr(trim($this->buffers[$dimension] ?? ''), 0, 191);
        if ($v !== '' && ! in_array($v, $this->values[$dimension], true)) {
            $this->values[$dimension][] = $v;
        }
        $this->buffers[$dimension] = '';
    }

    public function removeValue(string $dimension, int $index): void
    {
        if (isset($this->values[$dimension][$index])) {
            unset($this->values[$dimension][$index]);
            $this->values[$dimension] = array_values($this->values[$dimension]);
        }
    }

    public function selectGeo(int $locationId): void
    {
        $loc = SeoGeoLocation::find($locationId);
        if ($loc) {
            $this->geoLocationId = $loc->id;
            $this->geoName = $loc->name;
            $this->geoSearch = '';
        }
    }

    public function clearGeo(): void
    {
        $this->geoLocationId = null;
        $this->geoName = null;
    }

    public function save(): void
    {
        if ($this->persist()) {
            $this->show = false;
            $this->dispatch('url-target-saved', urlId: $this->urlId);
        }
    }

    /**
     * Dimensionen schreiben (Basis-Pflicht). Gibt false zurück, wenn ungültig
     * — der Fehler ist dann gesetzt. Geteilt von save() und buildBaseCluster().
     */
    protected function persist(): bool
    {
        if (! $this->urlId) {
            return false;
        }
        // Basis ist der Kern — Pflicht.
        if (empty($this->values['basis'])) {
            $this->addError('basis', 'Mindestens ein Basis-Begriff ist nötig (der Kern des Themas).');

            return false;
        }
        $this->resetErrorBag();

        $teamId = (int) $this->seoSettings->team_id;
        SeoUrlDimension::where('url_id', $this->urlId)->delete();

        $rows = [];
        foreach (['basis', 'anlass', 'typ', 'zielgruppe'] as $dim) {
            foreach ($this->values[$dim] as $value) {
                $rows[] = [
                    'url_id' => $this->urlId,
                    'team_id' => $teamId,
                    'dimension' => $dim,
                    'value' => $value,
                    'geo_location_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        if ($this->geoLocationId && $this->geoName) {
            $rows[] = [
                'url_id' => $this->urlId,
                'team_id' => $teamId,
                'dimension' => SeoUrlDimension::DIM_GEO,
                'value' => $this->geoName,
                'geo_location_id' => $this->geoLocationId,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if ($rows) {
            SeoUrlDimension::insert($rows);
        }

        return true;
    }

    /**
     * Speichern UND den gesperrten Basis-Cluster via DataForSEO erzeugen/frischen
     * (Basis × GEO → Seed-Expansion → Cluster). Modal bleibt offen, um das
     * Ergebnis zu zeigen.
     */
    public function buildBaseCluster(SeoBaseClusterBuilder $builder): void
    {
        $this->buildResult = null;
        $this->buildError = false;

        if (! $this->persist()) {
            return;
        }
        $this->dispatch('url-target-saved', urlId: $this->urlId);

        $url = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($this->urlId);
        if (! $url) {
            return;
        }

        $res = $builder->build($url);
        if (! empty($res['error'])) {
            $this->buildError = true;
            $this->buildResult = $res['error'];

            return;
        }

        $this->buildResult = sprintf(
            '✓ Basis-Cluster „%s": %d Marken-Anker · %d neu angehängt (%d gefunden), %d aus Bestand geordnet · Potenzial %s/Monat.',
            $res['cluster']->name ?? 'Basis-Cluster',
            $res['anchored'] ?? 0,
            $res['attached'] ?? 0,
            $res['fetched'] ?? 0,
            $res['swept'] ?? 0,
            number_format($res['potential'] ?? 0, 0, ',', '.'),
        );
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function render()
    {
        $geoResults = collect();
        $term = trim($this->geoSearch);
        if (strlen($term) >= 2) {
            $geoResults = SeoGeoLocation::where('name', 'like', '%'.$term.'%')
                // Exakter Ort zuerst (Name beginnt mit „Term," = die Stadt selbst,
                // nicht „Term-Stadtteil"), dann Ebene, dann kürzere (= weniger
                // spezifische) Namen — so steht Düsseldorf über Düsseldorf-Hafen.
                ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', [$term.',%'])
                ->orderByRaw("CASE level WHEN 'country' THEN 0 WHEN 'region' THEN 1 WHEN 'city' THEN 2 ELSE 3 END")
                ->orderByRaw('CHAR_LENGTH(name)')
                ->orderBy('name')
                ->limit(15)
                ->get();
        }

        return view('seo::livewire.url-seo-target', [
            'catalog' => SeoUrlDimension::catalog(),
            'geoResults' => $geoResults,
        ]);
    }
}
