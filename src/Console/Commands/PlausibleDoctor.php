<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Integrations\Services\IntegrationConnectionResolver;
use Platform\Integrations\Services\PlausibleApiService;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlTraffic;

/**
 * Plausible-Diagnose: macht den (bisher stillen) Datenzufluss sichtbar. Zeigt je
 * Team + eigener Domain — Connection-Status, Opt-in, site_id, vorhandene Historie
 * — und macht LIVE-Testcalls (normaler Breakdown + organischer Channel-Filter),
 * damit man sieht, WO es bricht, statt zu raten. Read-only außer den GET-Calls.
 *
 *   php artisan seo:plausible-doctor [--team=ID] [--domain=example.de]
 */
class PlausibleDoctor extends Command
{
    protected $signature = 'seo:plausible-doctor {--team= : Nur dieses Team} {--domain= : Nur diese Domain}';

    protected $description = 'Diagnose der Plausible-Anbindung: Connection, Opt-in, site_id, Historie + Live-Testcalls';

    public function handle(PlausibleApiService $plausibleApi, IntegrationConnectionResolver $resolver): int
    {
        $settingsQuery = SeoTeamSettings::query();
        if ($teamId = $this->option('team')) {
            $settingsQuery->where('team_id', $teamId);
        }
        $settingsList = $settingsQuery->get();

        if ($settingsList->isEmpty()) {
            $this->error('Keine SEO-Team-Settings gefunden.');

            return self::FAILURE;
        }

        $date = now()->subDay()->toDateString();
        $since = now()->subDays(30)->toDateString();
        $domainFilter = $this->option('domain') ? $this->normalizeDomain($this->option('domain')) : null;

        foreach ($settingsList as $settings) {
            $team = $settings->team;
            $this->newLine();
            $this->line("<fg=cyan;options=bold>Team {$settings->team_id}</> · {$settings->domain}");

            if (! $team) {
                $this->warn('  Kein Team-Objekt — übersprungen.');
                continue;
            }

            $connection = $resolver->resolveForTeam('plausible', $team);
            if (! $connection) {
                $this->error('  ✗ Keine aktive Plausible-Connection für dieses Team.');
                continue;
            }

            $this->line("  Connection #{$connection->id} · Status: <fg=".($connection->status === 'active' ? 'green' : 'red').">{$connection->status}</>"
                . ($connection->last_error ? " · letzter Fehler: {$connection->last_error}" : ''));

            $api = $plausibleApi->forConnection($connection->id);

            // Eigene Domains (opt-in-Zustand sichtbar machen).
            $ownRoots = SeoUrl::where('team_id', $team->id)
                ->where('is_own', true)
                ->get()
                ->groupBy(fn (SeoUrl $u) => $this->normalizeDomain($u->domain));

            $enabledCount = 0;

            foreach ($ownRoots as $domain => $urls) {
                if ($domainFilter && $domain !== $domainFilter) {
                    continue;
                }

                // Root = plausible_enabled-URL der Domain, sonst irgendeine.
                $root = $urls->firstWhere('plausible_enabled', true);
                $enabled = (bool) $root;
                if ($enabled) {
                    $enabledCount++;
                }
                $siteId = $root?->plausible_site_id ?: $domain;

                $urlIds = $urls->pluck('id')->all();
                $traffic = SeoUrlTraffic::whereIn('url_id', $urlIds)->where('source', 'plausible');
                $rows30d = (clone $traffic)->where('date', '>=', $since)->count();
                $latest = (clone $traffic)->max('date');

                $flag = $enabled ? '<fg=green>opt-in ✓</>' : '<fg=yellow>opt-in ✗ (wird NICHT gesammelt)</>';
                $this->newLine();
                $this->line("  <options=bold>{$domain}</> — {$flag} · site_id: <fg=magenta>{$siteId}</> · Historie: {$rows30d} Zeilen/30T · letzte: " . ($latest ?: '—'));

                if (! $enabled) {
                    $this->line('    → zum Sammeln: plausible_enabled=true + plausible_site_id an der Root-URL setzen.');
                    continue;
                }

                // LIVE-Testcall 1: normaler Breakdown.
                try {
                    $bd = $api->getBreakdown(null, [
                        'site_id' => $siteId, 'property' => 'event:page', 'period' => 'day',
                        'date' => $date, 'metrics' => 'visitors,pageviews', 'limit' => 5,
                    ]);
                    $n = count($bd['results'] ?? []);
                    $this->line("    <fg=green>✓ Breakdown</> ({$date}): {$n} Pfade" . ($n === 0 ? ' <fg=yellow>(leer — kein Traffic o. falsche site_id?)</>' : ''));
                } catch (\Throwable $e) {
                    $this->line("    <fg=red>✗ Breakdown fehlgeschlagen:</> " . $e->getMessage());
                }

                // LIVE-Testcall 2: organischer Channel-Filter (v2-Dimension auf v1-Endpoint = häufiger Bruch).
                try {
                    $org = $api->getBreakdown(null, [
                        'site_id' => $siteId, 'property' => 'event:page', 'period' => 'day',
                        'date' => $date, 'metrics' => 'visitors', 'limit' => 5,
                        'filters' => config('seo.plausible.organic_filter', 'visit:channel==Organic Search'),
                    ]);
                    $n = count($org['results'] ?? []);
                    $this->line("    <fg=green>✓ Organic-Filter</>: {$n} Pfade");
                } catch (\Throwable $e) {
                    $this->line("    <fg=red>✗ Organic-Filter fehlgeschlagen:</> " . $e->getMessage() . ' <fg=yellow>(→ v1/v2-Mismatch? „visit:channel" ist v2)</>');
                }
            }

            if ($enabledCount === 0) {
                $this->warn("  Keine einzige Domain opt-in — deshalb kommt hier nichts an.");
            }
        }

        $this->newLine();
        $this->line('<options=bold>Legende:</> opt-in ✗ = Domain nicht aktiviert (Hauptgrund für „keine Daten"). Organic-Filter ✗ = API-Version-Mismatch (v1 vs v2).');

        return self::SUCCESS;
    }

    protected function normalizeDomain(?string $domain): string
    {
        return preg_replace('/^www\./', '', strtolower(trim((string) $domain)));
    }
}
