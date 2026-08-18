<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Services\SeoPortfolioOrchestrator;

/**
 * Geplanter Per-Wirkungsraum-Lauf (~2-wöchentlich): Nachfrage laden → Entitäten
 * mergen → Maßnahmen erzeugen (v1 Board + v2 + KI). Macht die Steuer-Produktions-
 * linie autonom — der Posteingang füllt sich für JEDEN Wirkungsraum ohne Klick.
 */
class RefreshPortfolios extends Command
{
    protected $signature = 'seo:refresh-portfolios {--team= : nur ein Team} {--portfolio= : nur ein Wirkungsraum}';

    protected $description = 'Per-Wirkungsraum-Lauf: Nachfrage laden → Entitäten mergen → Maßnahmen erzeugen (v1+v2+KI)';

    public function handle(SeoPortfolioOrchestrator $orchestrator): int
    {
        $query = SeoPortfolio::query();
        if ($team = $this->option('team')) {
            $query->where('team_id', (int) $team);
        }
        if ($pid = $this->option('portfolio')) {
            $query->where('id', (int) $pid);
        }

        $portfolios = $query->get();
        $this->info($portfolios->count().' Wirkungsräume werden aufgefrischt.');

        foreach ($portfolios as $portfolio) {
            try {
                $r = $orchestrator->refresh($portfolio);
                $this->line("• {$portfolio->name}: {$r['demand']} Nachfrage · {$r['merged']} gemergt · {$r['measures']} neue Maßnahmen");
            } catch (\Throwable $e) {
                $this->error("• {$portfolio->name}: ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
