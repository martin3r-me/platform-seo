<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoKeywordService;
use Platform\Seo\Services\SeoUrlService;

class OnboardUrlTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.urls.onboarding.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/urls/onboarding - Vollständiges URL-Onboarding in einem Schritt: 1) URL registrieren, 2) Keywords der Domain discovern + importieren, 3) Rankings per Domain fetchen. Spart den manuellen 3-Schritt-Workflow. Verbraucht API-Budget!';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'URL zum Onboarden (z.B. "https://kullmans.de")',
                ],
                'is_own' => [
                    'type' => 'boolean',
                    'description' => 'Eigene URL (true, Standard) oder Wettbewerber (false)',
                ],
                'keywords_limit' => [
                    'type' => 'integer',
                    'description' => 'Max Keywords beim Discovery (Standard: 200)',
                ],
                'min_search_volume' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords mit SV >= X importieren (Standard: 0)',
                ],
                'skip_discover' => [
                    'type' => 'boolean',
                    'description' => 'Keyword-Discovery überspringen (nur Register + Rankings)',
                ],
                'skip_rankings' => [
                    'type' => 'boolean',
                    'description' => 'Rankings-Fetch überspringen (nur Register + Discovery)',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Nur Kosten schätzen, kein API-Call.',
                ],
            ],
            'required' => ['url'],
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

            $url = trim($arguments['url'] ?? '');
            if (empty($url)) {
                return ToolResult::error('URL ist erforderlich.', 'VALIDATION_ERROR');
            }

            $isOwn = $arguments['is_own'] ?? true;
            $keywordsLimit = (int) ($arguments['keywords_limit'] ?? 200);
            $minVolume = (int) ($arguments['min_search_volume'] ?? 0);
            $skipDiscover = $arguments['skip_discover'] ?? false;
            $skipRankings = $arguments['skip_rankings'] ?? false;
            $dryRun = $arguments['dry_run'] ?? false;

            // Domain aus URL extrahieren
            $parsed = parse_url($url, PHP_URL_HOST);
            if (!$parsed) {
                $parsed = parse_url('https://' . $url, PHP_URL_HOST);
            }
            $domain = $parsed ? preg_replace('/^www\./', '', $parsed) : null;

            if (!$domain) {
                return ToolResult::error('Konnte keine Domain aus URL extrahieren.', 'VALIDATION_ERROR');
            }

            if ($dryRun) {
                $steps = ['1. URL registrieren (kostenlos)'];
                $estimatedCost = 0;
                if (!$skipDiscover) {
                    $discoverCost = 10; // ~10 Cent für Domain-Discovery
                    $steps[] = "2. Keywords discovern (~{$discoverCost} Cent, max {$keywordsLimit} Keywords)";
                    $estimatedCost += $discoverCost;
                }
                if (!$skipRankings && $isOwn) {
                    $rankingsCost = 10; // ~10 Cent für getRankedKeywords pro Domain
                    $steps[] = '3. Rankings fetchen (~' . $rankingsCost . ' Cent, 1 API-Call)';
                    $estimatedCost += $rankingsCost;
                }

                return ToolResult::success([
                    'dry_run' => true,
                    'url' => $url,
                    'domain' => $domain,
                    'is_own' => $isOwn,
                    'steps' => $steps,
                    'estimated_cost_cents' => $estimatedCost,
                    'message' => "Onboarding für {$domain}: " . count($steps) . " Schritte, ~{$estimatedCost} Cent.",
                ]);
            }

            $results = [];
            $totalCost = 0;

            // Step 1: URL registrieren
            $urlService = app(SeoUrlService::class);
            $registerResult = $urlService->register($team->id, $url, [
                'is_own' => $isOwn,
                'reason' => 'onboarding',
            ]);
            $results['register'] = [
                'url_id' => $registerResult['url_id'] ?? null,
                'created' => $registerResult['created'] ?? false,
            ];

            // Step 2: Keywords discovern
            if (!$skipDiscover) {
                $keywordService = app(SeoKeywordService::class);
                try {
                    $discoverResult = $keywordService->discoverFromDomain(
                        $settings,
                        $domain,
                        $context->user,
                        $keywordsLimit,
                    );

                    $keywords = $discoverResult['keywords'] ?? [];
                    $discoverCost = $discoverResult['cost_cents'] ?? 0;
                    $totalCost += $discoverCost;

                    // Auto-Import
                    $toImport = [];
                    foreach ($keywords as $kw) {
                        $kwText = $kw['keyword'] ?? null;
                        if (!$kwText) {
                            continue;
                        }
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

                    $imported = 0;
                    if (!empty($toImport)) {
                        $imported = $keywordService->addKeywords($team->id, $toImport, $context->user)->count();
                    }

                    $results['discover'] = [
                        'discovered' => count($keywords),
                        'imported' => $imported,
                        'filtered_out' => count($keywords) - count($toImport),
                        'cost_cents' => $discoverCost,
                    ];
                } catch (\Throwable $e) {
                    $results['discover'] = ['error' => $e->getMessage()];
                }
            }

            // Step 3: Rankings fetchen (nur für eigene URLs)
            if (!$skipRankings && $isOwn) {
                $keywordService = $keywordService ?? app(SeoKeywordService::class);
                try {
                    $rankingsResult = $keywordService->fetchRankingsByDomain($team->id, $context->user, [
                        'domain' => $domain,
                        'keywords_limit' => min($keywordsLimit, 1000),
                    ]);

                    $rankingsCost = $rankingsResult['cost_cents'] ?? 0;
                    $totalCost += $rankingsCost;

                    $results['rankings'] = [
                        'fetched' => $rankingsResult['fetched'] ?? 0,
                        'position_snapshots' => $rankingsResult['position_snapshots'] ?? 0,
                        'urls_auto_created' => $rankingsResult['urls_auto_created'] ?? 0,
                        'cost_cents' => $rankingsCost,
                    ];
                } catch (\Throwable $e) {
                    $results['rankings'] = ['error' => $e->getMessage()];
                }
            }

            // Zusammenfassung
            $parts = [];
            if (isset($results['register'])) {
                $parts[] = $results['register']['created'] ? 'URL registriert' : 'URL existierte bereits';
            }
            if (isset($results['discover']['imported'])) {
                $parts[] = $results['discover']['imported'] . ' Keywords importiert';
            }
            if (isset($results['rankings']['fetched'])) {
                $parts[] = $results['rankings']['fetched'] . ' Rankings getrackt';
            }

            return ToolResult::success([
                'domain' => $domain,
                'steps' => $results,
                'total_cost_cents' => $totalCost,
                'message' => 'Onboarding abgeschlossen: ' . implode(', ', $parts) . " ({$totalCost} Cent).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
