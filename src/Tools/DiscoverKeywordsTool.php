<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoKeywordService;

class DiscoverKeywordsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.discover.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/keywords/discover - Entdeckt neue Keywords via DataForSEO. Entweder seed_keywords (Array) ODER domain angeben. Mit import=true werden die Keywords direkt in die DB importiert. Optional: limit (Standard: 100). Verbraucht API-Budget!';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'seed_keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Seed-Keywords für Related-Suche',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Domain für Keyword-Discovery (Alternative zu seed_keywords)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Anzahl Keywords (Standard: 100)',
                ],
                'import' => [
                    'type' => 'boolean',
                    'description' => 'Wenn true: Keywords direkt in seo_keywords importieren (Standard: false)',
                ],
                'min_search_volume' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords mit mindestens diesem Suchvolumen importieren (nur bei import=true)',
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

            $service = app(SeoKeywordService::class);
            $limit = (int) ($arguments['limit'] ?? 100);

            if (!empty($arguments['domain'])) {
                $result = $service->discoverFromDomain($settings, $arguments['domain'], $context->user, $limit);
            } elseif (!empty($arguments['seed_keywords'])) {
                $result = $service->discoverKeywords($settings, $arguments['seed_keywords'], $context->user, $limit);
            } else {
                return ToolResult::error('Entweder seed_keywords oder domain angeben.', 'VALIDATION_ERROR');
            }

            $keywords = $result['keywords'] ?? [];
            $importResult = null;

            // Auto-import if requested
            if (!empty($arguments['import']) && !empty($keywords)) {
                $minVolume = (int) ($arguments['min_search_volume'] ?? 0);

                $toImport = [];
                foreach ($keywords as $kw) {
                    $kwText = $kw['keyword'] ?? null;
                    if (!$kwText) {
                        continue;
                    }

                    // Filter by min search volume
                    if ($minVolume > 0 && ($kw['search_volume'] ?? 0) < $minVolume) {
                        continue;
                    }

                    $toImport[] = [
                        'keyword' => $kwText,
                        'search_volume' => $kw['search_volume'] ?? null,
                        'keyword_difficulty' => $kw['keyword_difficulty'] ?? null,
                        'cpc_cents' => isset($kw['cpc']) ? (int) round($kw['cpc'] * 100) : null,
                        'competition' => $kw['competition'] ?? null,
                    ];
                }

                if (!empty($toImport)) {
                    $imported = $service->addKeywords($team->id, $toImport, $context->user);
                    $importResult = [
                        'imported' => $imported->count(),
                        'filtered_out' => count($keywords) - count($toImport),
                    ];
                }
            }

            $response = [
                'discovered' => count($keywords),
                'cost_cents' => $result['cost_cents'] ?? 0,
            ];

            if ($importResult) {
                $response['import'] = $importResult;
                $response['message'] = $importResult['imported'] . ' Keywords importiert (' . count($keywords) . ' entdeckt).';
            } else {
                $response['keywords'] = $keywords;
                $response['message'] = count($keywords) . ' Keywords entdeckt. Nutze import=true zum Speichern.';
            }

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
