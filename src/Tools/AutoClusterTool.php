<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Jobs\DiscoverClustersJob;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoClusteringService;

class AutoClusterTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.clusters.auto.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/clusters/auto - Automatisches SERP-basiertes Keyword-Clustering. Gruppiert Keywords anhand überlappender SERP-Ergebnisse. Optional: url_id (nur Keywords dieser eigenen URL + Kinder clustern = kunden-scoped; neue Cluster hängen am Kunden-Knoten), min_overlap (Standard: 3). Ohne url_id werden ALLE noch nicht geclusterten Team-Keywords verarbeitet. Verbraucht API-Budget!';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords dieser eigenen URL (inkl. Kind-URLs) clustern. Empfohlen — hält Cluster pro Kunde und begrenzt die Kosten.',
                ],
                'min_overlap' => [
                    'type' => 'integer',
                    'description' => 'Minimale SERP-Überlappung für Cluster-Zuordnung (Standard: 3)',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $settings = SeoTeamSettings::where('team_id', $team->id)->first();
            if (!$settings) {
                return ToolResult::error('Keine SEO-Einstellungen für dieses Team konfiguriert.', 'NOT_CONFIGURED');
            }

            $minOverlap = (int) ($arguments['min_overlap'] ?? 3);

            // Kunden-Scope → Hintergrund-Job (SERP-Clustering läuft je nach
            // Keyword-Zahl lange und würde ein synchrones Timeout reißen).
            if (! empty($arguments['url_id'])) {
                $rootId = (int) $arguments['url_id'];
                $url = SeoUrl::where('id', $rootId)
                    ->where('team_id', $team->id)
                    ->where('is_own', true)
                    ->first();

                if (! $url) {
                    return ToolResult::error('URL nicht gefunden oder kein eigenes Asset.', 'NOT_FOUND');
                }

                $url->markClustering('running');
                DiscoverClustersJob::dispatch($rootId, $minOverlap);

                return ToolResult::success([
                    'status' => 'started',
                    'url_id' => $rootId,
                    'message' => 'Cluster-Discovery gestartet (läuft im Hintergrund). Fortschritt via clustering_status, Ergebnis via seo.clusters.GET.',
                ]);
            }

            // Ohne url_id: team-weiter Synchronlauf (nur für kleine Bestände).
            $result = app(SeoClusteringService::class)->autoCluster($settings, $context->user, $minOverlap);

            return ToolResult::success([
                'result' => $result,
                'message' => 'Auto-Clustering abgeschlossen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
