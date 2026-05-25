<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Services\SeoKeywordService;

class FetchSerpCompetitorsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.competitors.serp.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/competitors/serp - Holt SERP-Competitors für Keywords und persistiert Top-10 Domains pro Keyword in seo_keyword_competitors. Nutzt getSerpOrganic() = 1 API-Call pro Keyword (~10 Cent). Nutze dry_run=true für Kostenvorschau.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords dieser URL abfragen.',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Nur Keywords dieser Domain abfragen (z.B. "kullmans.de").',
                ],
                'min_volume' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords mit SV >= X abfragen (Kostenkontrolle).',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Maximale Anzahl Keywords (Default: alle).',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Nur Kosten schätzen, kein API-Call.',
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

            $options = [
                'url_id' => $arguments['url_id'] ?? null,
                'domain' => $arguments['domain'] ?? null,
                'min_volume' => $arguments['min_volume'] ?? null,
                'limit' => $arguments['limit'] ?? 0,
            ];

            // Build query to estimate keyword count
            $query = SeoKeyword::where('team_id', $team->id);
            if (!empty($options['url_id'])) {
                $query->whereHas('urls', fn ($q) => $q->where('seo_urls.id', $options['url_id']));
            }
            if (!empty($options['domain'])) {
                $query->whereHas('urls', fn ($q) => $q->where('domain', $options['domain']));
            }
            if (!empty($options['min_volume'])) {
                $query->where('search_volume', '>=', $options['min_volume']);
            }
            if ($options['limit'] > 0) {
                $query->limit($options['limit']);
            }
            $keywordCount = $query->count();

            if ($keywordCount === 0) {
                return ToolResult::success([
                    'keywords' => 0,
                    'message' => 'Keine Keywords gefunden für die gegebenen Filter.',
                ]);
            }

            $estimatedCostCents = $keywordCount * 10;

            if (!empty($arguments['dry_run'])) {
                return ToolResult::success([
                    'dry_run' => true,
                    'keywords' => $keywordCount,
                    'estimated_cost_cents' => $estimatedCostCents,
                    'estimated_cost_euro' => number_format($estimatedCostCents / 100, 2),
                    'filters' => array_filter($options),
                    'message' => "{$keywordCount} Keywords, geschätzte Kosten: {$estimatedCostCents} Cent ({$this->formatEuro($estimatedCostCents)}).",
                ]);
            }

            $service = app(SeoKeywordService::class);
            $result = $service->fetchRankings($team->id, $context->user, $options);

            if (!empty($result['error'])) {
                return ToolResult::error($result['error'], 'FETCH_ERROR');
            }

            return ToolResult::success([
                'fetched' => $result['fetched'],
                'position_snapshots' => $result['position_snapshots'],
                'cost_cents' => $result['cost_cents'],
                'top_competitors' => $result['top_competitors'] ?? [],
                'message' => $result['fetched'] . ' Keywords abgefragt, '
                    . $result['position_snapshots'] . ' Positionen getrackt, '
                    . $result['cost_cents'] . ' Cent Kosten. '
                    . 'Competitor-Daten in seo_keyword_competitors persistiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function formatEuro(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }
}
