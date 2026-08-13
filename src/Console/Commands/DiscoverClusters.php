<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoClusteringService;
use Platform\Seo\Services\SeoOrganizationLinker;

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

    public function handle(SeoClusteringService $service, SeoOrganizationLinker $linker): int
    {
        $rootId = (int) $this->argument('url_id');
        $root = SeoUrl::find($rootId);
        if (! $root) {
            $this->error("URL {$rootId} nicht gefunden.");

            return self::FAILURE;
        }

        $settings = SeoTeamSettings::where('team_id', $root->team_id)->first();
        if (! $settings) {
            $this->error("Keine SEO-Einstellungen für Team {$root->team_id}.");

            return self::FAILURE;
        }

        $childIds = SeoUrlRelationship::where('source_url_id', $rootId)
            ->where('type', 'parent_child')
            ->pluck('target_url_id')->all();

        $urlIds = SeoUrl::whereIn('id', array_merge([$rootId], $childIds))
            ->where('team_id', $root->team_id)
            ->where('is_own', true)
            ->pluck('id')->all();

        if (empty($urlIds)) {
            $this->error('Keine eigenen URLs im Scope.');

            return self::FAILURE;
        }

        $entityId = $linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_URL, $rootId)[0] ?? null;

        $this->info("Clustering {$root->url} — ".count($urlIds).' URL(s), Knoten '.($entityId ?? '—').' …');

        $result = $service->autoCluster($settings, null, (int) $this->option('min-overlap'), $urlIds, $entityId);

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
