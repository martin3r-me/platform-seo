<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;

class UpdateUrlTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.urls.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /seo/urls - Aktualisiert Eigenschaften einer oder mehrerer SEO-URLs. Typischer Use-Case: is_own ändern (eigene ↔ Wettbewerber), Priorität setzen, Status ändern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Einzelne URL-ID zum Aktualisieren',
                ],
                'url_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von URL-IDs für Bulk-Update',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Alle URLs dieser Domain aktualisieren',
                ],
                'is_own' => [
                    'type' => 'boolean',
                    'description' => 'Eigene URL (true) oder Wettbewerber (false)',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'redirected', 'deleted', 'error'],
                ],
                'priority' => [
                    'type' => 'integer',
                    'description' => 'Priorität (0-100)',
                ],
                'data_profile' => [
                    'type' => 'string',
                    'description' => 'Daten-Profil. Eigene URLs: aus/basis/standard/tief. Wettbewerber: aus/beobachten/analyse. Wird je URL gegen die passende Leiter validiert.',
                ],
                'boost_days' => [
                    'type' => 'integer',
                    'description' => 'Boost: N Tage täglich SERP (0 = Boost beenden).',
                ],
                'plausible_enabled' => [
                    'type' => 'boolean',
                    'description' => 'Plausible-Opt-in: Traffic/Conversions für diese Domain sammeln.',
                ],
                'plausible_site_id' => [
                    'type' => 'string',
                    'description' => 'Plausible-Site-Name, falls ≠ Domain (z.B. „broichcatering.com" für Domain „broich.catering"). Leerer String = zurück auf Domain-Fallback.',
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

            $query = SeoUrl::where('team_id', $team->id);

            if (!empty($arguments['url_id'])) {
                $query->where('id', (int) $arguments['url_id']);
            } elseif (!empty($arguments['url_ids'])) {
                $query->whereIn('id', array_map('intval', $arguments['url_ids']));
            } elseif (!empty($arguments['domain'])) {
                $query->where('domain', $arguments['domain']);
            } else {
                return ToolResult::error('Mindestens url_id, url_ids oder domain angeben.', 'VALIDATION_ERROR');
            }

            $urls = $query->get();

            if ($urls->isEmpty()) {
                return ToolResult::error('Keine URLs gefunden.', 'NOT_FOUND');
            }

            $updates = [];
            if (isset($arguments['is_own'])) {
                $updates['is_own'] = (bool) $arguments['is_own'];
            }
            if (isset($arguments['status'])) {
                $updates['status'] = $arguments['status'];
            }
            if (isset($arguments['priority'])) {
                $updates['priority'] = max(0, min(100, (int) $arguments['priority']));
            }

            // Boost: N Tage täglich SERP (0 = beenden).
            if (array_key_exists('boost_days', $arguments)) {
                $days = (int) $arguments['boost_days'];
                $updates['boost_until'] = $days > 0 ? now()->addDays($days) : null;
            }

            if (isset($arguments['plausible_enabled'])) {
                $updates['plausible_enabled'] = (bool) $arguments['plausible_enabled'];
            }
            if (array_key_exists('plausible_site_id', $arguments)) {
                $sid = trim((string) $arguments['plausible_site_id']);
                $updates['plausible_site_id'] = $sid !== '' ? $sid : null;
            }

            $wantsProfile = array_key_exists('data_profile', $arguments);
            $profileSvc = $wantsProfile ? app(\Platform\Seo\Services\SeoDataProfileService::class) : null;
            $profileSkipped = [];

            if (empty($updates) && !$wantsProfile) {
                return ToolResult::error('Keine Änderungen angegeben.', 'VALIDATION_ERROR');
            }

            foreach ($urls as $url) {
                $perUrl = $updates;

                // data_profile je URL gegen die passende Leiter (is_own) validieren.
                if ($wantsProfile) {
                    $isOwn = $perUrl['is_own'] ?? (bool) $url->is_own;
                    if ($profileSvc->isValidProfile($isOwn, $arguments['data_profile'])) {
                        $perUrl['data_profile'] = $arguments['data_profile'];
                    } else {
                        $profileSkipped[] = $url->id;
                    }
                }

                if (!empty($perUrl)) {
                    $url->update($perUrl);
                }
            }

            return ToolResult::success(array_filter([
                'updated' => $urls->count(),
                'url_ids' => $urls->pluck('id')->all(),
                'changes' => $updates,
                'data_profile' => $wantsProfile ? $arguments['data_profile'] : null,
                'profile_skipped_invalid_ladder' => $profileSkipped ?: null,
                'message' => $urls->count() . ' URL(s) aktualisiert.'
                    . ($profileSkipped ? ' (' . count($profileSkipped) . ' Profil-Zuweisung(en) übersprungen: falsche Leiter für is_own.)' : ''),
            ], fn ($v) => $v !== null && $v !== []));
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
