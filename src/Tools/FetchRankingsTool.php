<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Illuminate\Support\Facades\Cache;
use Platform\Seo\Jobs\FetchRankingsJob;
use Platform\Seo\Services\SeoKeywordService;

class FetchRankingsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.rankings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/keywords/rankings - Holt Rankings per Domain via getRankedKeywords() (1 API-Call pro Domain statt pro Keyword). Upsert Keywords mit Metriken, ordnet Rankings per URL-Pfad zu, erstellt Position-Snapshots. Nutze dry_run=true um Kosten vorher zu sehen. Der echte Abruf läuft im Hintergrund (Queue) — Ergebnis/Fortschritt via status=true.';
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
                    'description' => 'Max Keywords pro Domain vom API (Default: 1000).',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Nur Domains + geschätzte Kosten anzeigen, kein API-Call.',
                ],
                'status' => [
                    'type' => 'boolean',
                    'description' => 'Nur Status/Ergebnis des letzten Hintergrund-Abrufs zurückgeben, nichts starten.',
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
                'domain' => $arguments['domain'] ?? null,
                'max_urls' => $arguments['max_urls'] ?? null,
                'keywords_limit' => $arguments['keywords_limit'] ?? 1000,
                'dry_run' => $arguments['dry_run'] ?? false,
            ];

            $current = Cache::get(FetchRankingsJob::statusKey($team->id));

            if (!empty($arguments['status'])) {
                return ToolResult::success($current ?? ['status' => 'none', 'message' => 'Kein Ranking-Abruf gelaufen.']);
            }

            // Echter Abruf → Hintergrund-Job: synchron riss das Upserten von
            // ~1000 Keywords/Domain das Gateway-Timeout („Bad Gateway").
            if (!$options['dry_run']) {
                // „running" älter als das Job-Timeout = toter Worker → neu starten erlaubt.
                $running = ($current['status'] ?? null) === 'running'
                    && now()->subSeconds(FetchRankingsJob::TIMEOUT)->lt($current['updated_at']);
                if ($running) {
                    return ToolResult::success($current + ['message' => 'Ranking-Abruf läuft bereits — Ergebnis via status=true.']);
                }

                FetchRankingsJob::markStatus($team->id, 'running', ['options' => $options]);
                FetchRankingsJob::dispatch($team->id, $context->user?->id, $options);

                return ToolResult::success([
                    'status' => 'started',
                    'message' => 'Ranking-Abruf gestartet (läuft im Hintergrund). Ergebnis via seo.keywords.rankings.POST mit status=true.',
                ]);
            }

            $result = app(SeoKeywordService::class)->fetchRankingsByDomain($team->id, $context->user, $options);

            if (!empty($result['error'])) {
                return ToolResult::error($result['error'], 'FETCH_ERROR');
            }

            return ToolResult::success([
                'dry_run' => true,
                'domains' => $result['domains'],
                'domain_count' => $result['domain_count'],
                'urls_count' => $result['urls_count'],
                'keywords_limit_per_domain' => $result['keywords_limit_per_domain'],
                'estimated_cost_cents' => $result['estimated_cost_cents'],
                'message' => $result['domain_count'] . ' Domain(s), ' . $result['urls_count'] . ' URLs. Geschätzte Kosten: ' . $result['estimated_cost_cents'] . ' Cent.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
