<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoKeywordService;

class FetchRankingsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.rankings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/keywords/rankings - Holt Rankings per Domain via getRankedKeywords() (1 API-Call pro Domain statt pro Keyword). Upsert Keywords mit Metriken, ordnet Rankings per URL-Pfad zu, erstellt Position-Snapshots. Nutze dry_run=true um Kosten vorher zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'domain' => [
                    'type' => 'string',
                    'description' => 'Nur eine bestimmte Domain abfragen (z.B. "lemonpie.de"). Ohne: alle eigenen Domains.',
                ],
                'max_urls' => [
                    'type' => 'integer',
                    'description' => 'Maximale Anzahl eigener URLs (Default: alle).',
                ],
                'keywords_limit' => [
                    'type' => 'integer',
                    'description' => 'Max Keywords pro Domain vom API (Default: 500).',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Nur Domains + geschätzte Kosten anzeigen, kein API-Call.',
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

            $service = app(SeoKeywordService::class);
            $result = $service->fetchRankingsByDomain($team->id, $context->user, [
                'domain' => $arguments['domain'] ?? null,
                'max_urls' => $arguments['max_urls'] ?? null,
                'keywords_limit' => $arguments['keywords_limit'] ?? 500,
                'dry_run' => $arguments['dry_run'] ?? false,
            ]);

            if (!empty($result['error'])) {
                return ToolResult::error($result['error'], 'FETCH_ERROR');
            }

            if (!empty($result['dry_run'])) {
                return ToolResult::success([
                    'dry_run' => true,
                    'domains' => $result['domains'],
                    'domain_count' => $result['domain_count'],
                    'urls_count' => $result['urls_count'],
                    'keywords_limit_per_domain' => $result['keywords_limit_per_domain'],
                    'estimated_cost_cents' => $result['estimated_cost_cents'],
                    'message' => $result['domain_count'] . ' Domain(s), ' . $result['urls_count'] . ' URLs. Geschätzte Kosten: ' . $result['estimated_cost_cents'] . ' Cent.',
                ]);
            }

            return ToolResult::success([
                'fetched' => $result['fetched'],
                'position_snapshots' => $result['position_snapshots'],
                'api_calls' => $result['api_calls'],
                'cost_cents' => $result['cost_cents'],
                'urls_updated' => $result['urls_updated'],
                'domains' => $result['domains'],
                'message' => $result['fetched'] . ' Keywords upserted, '
                    . $result['position_snapshots'] . ' Positionen getrackt, '
                    . $result['api_calls'] . ' API-Call(s) ('
                    . $result['cost_cents'] . ' Cent).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
