<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Models\SeoGeoLocation;
use Platform\Seo\Models\SeoTeamSettings;

/**
 * Spiegelt DataForSEOs Orts-Katalog eines Landes in seo_geo_locations, damit
 * die GEO-Dimension aus exakten location_codes wählt statt Freitext getippt zu
 * werden. Referenzdaten sind global — jede gültige DataForSEO-Connection
 * reicht; der Lauf ist selten/einmalig (Codes ändern sich kaum).
 */
class SyncGeoCatalog extends Command
{
    protected $signature = 'seo:geo-sync
                            {--country=DE : ISO-Land, dessen Orts-Teilbaum geladen wird}
                            {--connection= : Explizite DataForSEO-Connection-ID (sonst erste verfügbare)}';

    protected $description = 'Synchronisiert den DataForSEO-Orts-Katalog (Geo-Dimension) in seo_geo_locations.';

    public function handle(DataForSeoApiService $dfs): int
    {
        $country = strtoupper((string) $this->option('country'));

        $connectionId = $this->option('connection')
            ? (int) $this->option('connection')
            : $this->firstAvailableConnectionId();

        if (! $connectionId) {
            $this->error('Keine DataForSEO-Connection gefunden. --connection=<id> angeben oder ein Team mit DataForSEO-Verbindung einrichten.');

            return self::FAILURE;
        }

        $this->info("Lade Orts-Katalog für {$country} (Connection {$connectionId}) …");

        try {
            $locations = $dfs->forConnection($connectionId)->getLocations(null, $country);
        } catch (\Throwable $e) {
            $this->error('DataForSEO-Fehler: '.$e->getMessage());

            return self::FAILURE;
        }

        if (empty($locations)) {
            $this->warn('Keine Orte zurückgegeben.');

            return self::SUCCESS;
        }

        $upserted = 0;
        $skipped = 0;
        foreach ($locations as $loc) {
            $code = $loc['location_code'] ?? null;
            $name = $loc['location_name'] ?? null;
            if (! $code || ! $name) {
                continue;
            }

            $level = SeoGeoLocation::normalizeLevel($loc['location_type'] ?? null);
            if ($level === null) {
                // Nicht-geografisch (PLZ/Flughafen/Uni/DMA…) — nicht in den Picker.
                $skipped++;

                continue;
            }

            SeoGeoLocation::updateOrCreate(
                ['code' => (int) $code],
                [
                    'name' => (string) $name,
                    'country_iso' => $loc['country_iso_code'] ?? null,
                    'type' => $loc['location_type'] ?? null,
                    'level' => $level,
                ],
            );
            $upserted++;
        }

        // Alt-Bestand ohne Ebene (aus früheren Läufen vor dem Filter) aufräumen.
        $removed = SeoGeoLocation::whereNull('level')->delete();

        $this->info("Fertig: {$upserted} Orte gespeichert, {$skipped} nicht-geografische übersprungen".($removed ? ", {$removed} Alt-Einträge ohne Ebene entfernt" : '').'.');
        foreach (SeoGeoLocation::selectRaw('level, COUNT(*) as n')->groupBy('level')->orderByDesc('n')->get() as $row) {
            $this->line('  '.($row->level ?? '—').': '.$row->n);
        }

        return self::SUCCESS;
    }

    /**
     * Erste Team-Einstellung mit auflösbarer DataForSEO-Connection — die
     * Ortsdaten sind länderweit gleich, also ist die Quelle egal.
     */
    protected function firstAvailableConnectionId(): ?int
    {
        foreach (SeoTeamSettings::all() as $settings) {
            $id = $settings->resolveConnectionId();
            if ($id) {
                return $id;
            }
        }

        return null;
    }
}
