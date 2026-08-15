<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Services\SeoCostProjectionService;
use Platform\Seo\Services\SeoDataProfileService;

/**
 * Monatliche Datenbeschaffungs-Kosten aus den Daten-Profilen — je URL, Liste
 * oder gesamt im Team (inkl. Budget-Abgleich).
 */
class DataCostsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.costs.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/costs - Monatliche Datenbeschaffungs-Kosten aus den Daten-Profilen. '
            . 'Ohne Parameter: Team-Gesamt (eigen/Wettbewerber, vs. Budget). Optional: url_id (Profil + '
            . 'Collector-Aufschlüsselung) oder list_id (Listen-Summe).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => ['type' => 'integer', 'description' => 'Kosten + Profil einer URL'],
                'list_id' => ['type' => 'integer', 'description' => 'Kosten einer Liste'],
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

            $cost = app(SeoCostProjectionService::class);
            $profiles = app(SeoDataProfileService::class);

            if (!empty($arguments['url_id'])) {
                $url = SeoUrl::where('team_id', $team->id)->find((int) $arguments['url_id']);
                if (!$url) {
                    return ToolResult::error('URL nicht gefunden.', 'NOT_FOUND');
                }
                $monthly = $cost->urlMonthlyCents($url);

                return ToolResult::success([
                    'url' => $url->url,
                    'is_own' => (bool) $url->is_own,
                    'profile' => $profiles->effectiveProfile($url),
                    'boost_active' => $url->isBoostActive(),
                    'breakdown' => $cost->urlBreakdown($url),
                    'monthly_cents' => $monthly,
                    'monthly_euro' => number_format($monthly / 100, 2),
                ]);
            }

            if (!empty($arguments['list_id'])) {
                $list = SeoUrlList::where('team_id', $team->id)->find((int) $arguments['list_id']);
                if (!$list) {
                    return ToolResult::error('Liste nicht gefunden.', 'NOT_FOUND');
                }
                $monthly = $cost->listMonthlyCents($list);

                return ToolResult::success([
                    'list' => $list->name,
                    'default_data_profile' => $list->default_data_profile,
                    'url_count' => $list->urls()->count(),
                    'monthly_cents' => $monthly,
                    'monthly_euro' => number_format($monthly / 100, 2),
                ]);
            }

            $summary = $cost->teamSummary($team->id);
            $settings = SeoTeamSettings::where('team_id', $team->id)->first();
            $budget = $settings?->budget_limit_cents;

            return ToolResult::success(array_merge($summary, [
                'monthly_euro' => number_format($summary['monthly_cents'] / 100, 2),
                'budget_limit_cents' => $budget,
                'budget_used_pct' => ($budget && $budget > 0)
                    ? round($summary['monthly_cents'] / $budget * 100, 1)
                    : null,
                'message' => 'Team-Monatskosten: ' . number_format($summary['monthly_cents'] / 100, 2) . ' € '
                    . '(eigen ' . number_format($summary['own_cents'] / 100, 2) . ' €, '
                    . 'Wettbewerber ' . number_format($summary['competitor_cents'] / 100, 2) . ' €).',
            ]));
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
