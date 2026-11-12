<?php

namespace Platform\Seo\Tools;

use Illuminate\Support\Facades\Cache;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Jobs\FetchSerpCompetitorsJob;
use Platform\Seo\Models\SeoKeyword;

class FetchSerpCompetitorsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.competitors.serp.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/competitors/serp - Holt SERP-Competitors für Keywords und persistiert Top-10 Domains pro Keyword in seo_keyword_competitors. 1 SERP-Call pro Keyword (~10 Cent). Pflicht: url_id oder domain (sonst würde das ganze Team abgefragt). Nutze dry_run=true für Kostenvorschau. Der echte Abruf läuft im Hintergrund (Queue) — Ergebnis via status=true.';
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
                    'description' => 'Maximale Anzahl Keywords, die volumenstärksten zuerst (Default: alle).',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Nur Kosten schätzen, kein API-Call.',
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

            $current = Cache::get(FetchSerpCompetitorsJob::statusKey($team->id));

            if (!empty($arguments['status'])) {
                return ToolResult::success($current ?? ['status' => 'none', 'message' => 'Kein Wettbewerber-Abruf gelaufen.']);
            }

            // Ohne Scope liefen min_volume/limit über ALLE Keywords des Teams
            // (alle Projekte) — teuer und fachlich falsch.
            if (empty($arguments['url_id']) && empty($arguments['domain'])) {
                return ToolResult::error('url_id oder domain ist erforderlich — sonst würden Keywords aller Projekte des Teams abgefragt.', 'MISSING_SCOPE');
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
            $keywordCount = $query->count();
            if ($options['limit'] > 0) {
                $keywordCount = min($keywordCount, $options['limit']);
            }

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

            // Echter Abruf → Hintergrund-Job (synchron riss das Gateway-Timeout).
            // „running" älter als das Job-Timeout = toter Worker → neu starten erlaubt.
            $running = ($current['status'] ?? null) === 'running'
                && now()->subSeconds(FetchSerpCompetitorsJob::TIMEOUT)->lt($current['updated_at']);
            if ($running) {
                return ToolResult::success($current + ['message' => 'Wettbewerber-Abruf läuft bereits — Ergebnis via status=true.']);
            }

            FetchSerpCompetitorsJob::markStatus($team->id, 'running', ['options' => $options, 'keywords' => $keywordCount]);
            FetchSerpCompetitorsJob::dispatch($team->id, $context->user?->id, $options);

            return ToolResult::success([
                'status' => 'started',
                'keywords' => $keywordCount,
                'estimated_cost_cents' => $estimatedCostCents,
                'message' => "Wettbewerber-Abruf für {$keywordCount} Keywords gestartet (läuft im Hintergrund). Ergebnis via seo.competitors.serp.POST mit status=true.",
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
