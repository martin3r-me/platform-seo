<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Services\SeoClusteringService;

/**
 * Der „Entdecken"-Takt (Playbook §2): SERP-basiertes Auto-Clustering für eine
 * eigene URL (kunden-scoped). Läuft im CLI ohne HTTP-Timeout und ist damit für
 * reale Keyword-Mengen (Dutzende SERP-Fetches) geeignet — anders als der
 * synchrone MCP-Tool. Neue Cluster entstehen als `candidate` am Kunden-Knoten.
 */
class DiscoverClusters extends Command
{
    protected $signature = 'seo:discover-clusters
                            {url_id : Eigene URL, deren Keywords (inkl. Kinder) geclustert werden}
                            {--min-overlap=3 : Minimale SERP-Überlappung}';

    protected $description = 'SERP-basiertes Auto-Clustering für eine URL (kunden-scoped, ohne Timeout)';

    public function handle(SeoClusteringService $service): int
    {
        $rootId = (int) $this->argument('url_id');

        $this->info("Clustering für URL {$rootId} …");

        $result = $service->autoClusterForUrl($rootId, (int) $this->option('min-overlap'));

        if (! empty($result['error'])) {
            $this->error("Abgebrochen: {$result['error']}");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Fertig: %d Cluster · %d Keywords geclustert · %d SERP-Fetches · %d Singletons · %d ct.',
            $result['clusters_created'] ?? 0,
            $result['keywords_clustered'] ?? 0,
            $result['keywords_fetched'] ?? 0,
            $result['singletons_remaining'] ?? 0,
            $result['cost_cents'] ?? 0,
        ));

        foreach ($result['clusters'] ?? [] as $c) {
            $this->line("  • {$c['name']} ({$c['keyword_count']} KW)");
        }

        return self::SUCCESS;
    }
}
