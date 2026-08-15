<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoWirkungsraum;

/**
 * Hängt kontrollierte URLs an einen Wirkungsraum (oder entfernt sie). Steuer-
 * Invariante: nur EIGENE URLs (is_own) — man kann nur steuern, was man
 * kontrolliert. Fremde/Wettbewerber-URLs gehören in eine Liste (Beobachtung).
 */
class WirkungsraumUrlsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.wirkungsraum_urls.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/wirkungsraum-urls - Hängt kontrollierte URLs an einen Wirkungsraum (action add|remove). '
            . 'Parameter: wirkungsraum_id (required), url_ids (required, Array). Optional: role (core|support), '
            . 'action (Default add). NUR eigene URLs (is_own) — Wettbewerber/fremde URLs werden abgelehnt (die '
            . 'gehören in eine Liste = Beobachtung).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'wirkungsraum_id' => ['type' => 'integer'],
                'url_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                'role' => ['type' => 'string', 'description' => 'core|support (Owner vs. Zulieferer)'],
                'action' => ['type' => 'string', 'description' => 'add (Standard) oder remove'],
            ],
            'required' => ['wirkungsraum_id', 'url_ids'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $wr = SeoWirkungsraum::where('team_id', $team->id)->find((int) ($arguments['wirkungsraum_id'] ?? 0));
            if (!$wr) {
                return ToolResult::error('Wirkungsraum nicht gefunden.', 'NOT_FOUND');
            }

            $urlIds = array_map('intval', (array) ($arguments['url_ids'] ?? []));
            if (empty($urlIds)) {
                return ToolResult::error('url_ids ist erforderlich.', 'VALIDATION_ERROR');
            }
            $action = (string) ($arguments['action'] ?? 'add');

            if ($action === 'remove') {
                $wr->urls()->detach($urlIds);

                return ToolResult::success([
                    'wirkungsraum_id' => $wr->id,
                    'removed' => count($urlIds),
                    'message' => count($urlIds) . ' URL(s) aus Wirkungsraum entfernt.',
                ]);
            }

            // Nur eigene, kontrollierte URLs des Teams zulassen.
            $urls = SeoUrl::where('team_id', $team->id)->whereIn('id', $urlIds)->get();
            $ownIds = $urls->where('is_own', true)->pluck('id')->all();
            $rejected = $urls->where('is_own', false)->pluck('domain')->all();
            $missing = array_values(array_diff($urlIds, $urls->pluck('id')->all()));

            if (empty($ownIds)) {
                return ToolResult::error(
                    'Keine kontrollierten (eigenen) URLs dabei. Wirkungsraum steuert nur Eigenes; '
                        . 'fremde URLs gehören in eine Liste.',
                    'VALIDATION_ERROR',
                );
            }

            $role = $arguments['role'] ?? null;
            $wr->urls()->syncWithoutDetaching(
                collect($ownIds)->mapWithKeys(fn ($id) => [$id => ['role' => $role]])->all()
            );

            return ToolResult::success([
                'wirkungsraum_id' => $wr->id,
                'added' => count($ownIds),
                'rejected_competitors' => $rejected,
                'not_found' => $missing,
                'total_urls' => $wr->urls()->count(),
                'message' => count($ownIds) . ' kontrollierte URL(s) angehängt'
                    . (count($rejected) ? ', ' . count($rejected) . ' Wettbewerber abgelehnt' : '')
                    . (count($missing) ? ', ' . count($missing) . ' nicht gefunden' : '') . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
