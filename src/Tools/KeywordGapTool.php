<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoKeywordService;

/**
 * Keyword-Gap: was ein Wettbewerber rankt, das die eigene Domain nicht rankt
 * (Domain-Intersection). Die schnellste Content-Chancen-Liste — v.a. für
 * Null-Start-Kunden.
 */
class KeywordGapTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.competitors.gap.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/competitors/gap - Keyword-Gap via Domain-Intersection: Keywords, für die der '
            . 'Wettbewerber rankt, die eigene Domain aber NICHT. 1 API-Call (~10 Cent). '
            . 'Parameter: competitor_domain (required). Optional: own_domain (Default: erste eigene Domain), '
            . 'min_volume, limit, import (Gap-Keywords in den Pool aufnehmen).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'competitor_domain' => ['type' => 'string', 'description' => 'Wettbewerber-Domain (z.B. "kaerhealth.com")'],
                'own_domain' => ['type' => 'string', 'description' => 'Eigene Domain (Default: erste eigene URL)'],
                'min_volume' => ['type' => 'integer'],
                'limit' => ['type' => 'integer', 'description' => 'Max. Keywords (Default 100)'],
                'import' => ['type' => 'boolean', 'description' => 'Gap-Keywords in den Team-Pool aufnehmen'],
            ],
            'required' => ['competitor_domain'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            if (empty($arguments['competitor_domain'])) {
                return ToolResult::error('competitor_domain ist erforderlich.', 'VALIDATION_ERROR');
            }

            $result = app(SeoKeywordService::class)->keywordGap(
                $team->id,
                $arguments['competitor_domain'],
                $arguments['own_domain'] ?? null,
                $context->user,
                [
                    'min_volume' => $arguments['min_volume'] ?? 0,
                    'limit' => $arguments['limit'] ?? 100,
                    'import' => $arguments['import'] ?? false,
                ],
            );

            if (!empty($result['error'])) {
                return ToolResult::error($result['error'], 'FETCH_ERROR');
            }

            $result['message'] = "{$result['gap_count']} Gap-Keywords ({$result['competitor_domain']} rankt, {$result['own_domain']} nicht)"
                . ($result['imported'] ? ", {$result['imported']} importiert" : '')
                . " ({$result['cost_cents']} Cent).";

            return ToolResult::success($result);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
