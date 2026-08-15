<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoContentBrief;
use Platform\Seo\Models\SeoContentBriefLink;

/**
 * Verknüpft zwei Content-Briefs (internes Verlinken) — die Hub-and-Spoke-Struktur
 * explizit machen: Pillar → Spokes und zurück. Aktiviert das seo_content_brief_links-
 * Modell (aus brands portiert), das bislang brachlag.
 */
class LinkContentBriefsTool implements ToolContract
{
    private const TYPES = ['pillar_to_cluster', 'cluster_to_pillar', 'related', 'see_also'];

    public function getName(): string
    {
        return 'seo.content_briefs.link.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/content-briefs/link - Verlinkt zwei Content-Briefs intern (Hub-and-Spoke). '
            . 'Parameter: source_brief_id (required), target_brief_id (required), '
            . 'link_type (pillar_to_cluster|cluster_to_pillar|related|see_also, Standard related). '
            . 'Optional: anchor_hint (Anker-Text-Vorschlag), reciprocal (Default true → legt auch den Rück-Link an). '
            . 'Ideal um aus einem Pillar-Brief die Spoke-Briefs zu verknüpfen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'source_brief_id' => ['type' => 'integer', 'description' => 'Quell-Brief (z.B. der Pillar)'],
                'target_brief_id' => ['type' => 'integer', 'description' => 'Ziel-Brief (z.B. der Spoke)'],
                'link_type' => ['type' => 'string', 'enum' => self::TYPES, 'description' => 'Art der Verlinkung (Standard: related)'],
                'anchor_hint' => ['type' => 'string', 'description' => 'Vorschlag für den Anker-Text'],
                'reciprocal' => ['type' => 'boolean', 'description' => 'Auch den Rück-Link anlegen (Standard true)'],
            ],
            'required' => ['source_brief_id', 'target_brief_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $sourceId = (int) ($arguments['source_brief_id'] ?? 0);
            $targetId = (int) ($arguments['target_brief_id'] ?? 0);
            if (!$sourceId || !$targetId) {
                return ToolResult::error('source_brief_id und target_brief_id sind erforderlich.', 'VALIDATION_ERROR');
            }
            if ($sourceId === $targetId) {
                return ToolResult::error('Ein Brief kann nicht mit sich selbst verlinkt werden.', 'VALIDATION_ERROR');
            }

            $linkType = (string) ($arguments['link_type'] ?? 'related');
            if (!in_array($linkType, self::TYPES, true)) {
                return ToolResult::error('Ungültiger link_type. Erlaubt: ' . implode(', ', self::TYPES), 'VALIDATION_ERROR');
            }
            $anchor = $arguments['anchor_hint'] ?? null;
            $reciprocal = (bool) ($arguments['reciprocal'] ?? true);

            $briefs = SeoContentBrief::where('team_id', $team->id)
                ->whereIn('id', [$sourceId, $targetId])
                ->get()
                ->keyBy('id');

            if (!$briefs->has($sourceId) || !$briefs->has($targetId)) {
                return ToolResult::error('Quell- oder Ziel-Brief nicht gefunden (im Team).', 'NOT_FOUND');
            }

            $userId = $context->user?->id;

            // Reziproke Gegen-Typen für den Rück-Link.
            $inverse = [
                'pillar_to_cluster' => 'cluster_to_pillar',
                'cluster_to_pillar' => 'pillar_to_cluster',
                'related' => 'related',
                'see_also' => 'see_also',
            ][$linkType];

            $link = SeoContentBriefLink::firstOrCreate(
                [
                    'source_content_brief_id' => $sourceId,
                    'target_content_brief_id' => $targetId,
                    'link_type' => $linkType,
                ],
                ['anchor_hint' => $anchor, 'team_id' => $team->id, 'user_id' => $userId],
            );

            $created = 1;
            if ($reciprocal) {
                SeoContentBriefLink::firstOrCreate(
                    [
                        'source_content_brief_id' => $targetId,
                        'target_content_brief_id' => $sourceId,
                        'link_type' => $inverse,
                    ],
                    ['anchor_hint' => null, 'team_id' => $team->id, 'user_id' => $userId],
                );
                $created = 2;
            }

            return ToolResult::success([
                'link_id' => $link->id,
                'source_brief_id' => $sourceId,
                'target_brief_id' => $targetId,
                'link_type' => $linkType,
                'reciprocal' => $reciprocal,
                'links_created' => $created,
                'message' => "Brief #{$sourceId} → #{$targetId} verlinkt ({$linkType})"
                    . ($reciprocal ? " + Rück-Link ({$inverse})" : '') . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
