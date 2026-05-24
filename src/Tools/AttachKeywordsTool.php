<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoUrl;

class AttachKeywordsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.url-keywords.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/url-keywords - Verknüpft Keywords mit URLs (seo_url_keywords Pivot). Keywords können per ID, Text oder "all" angehängt werden. URLs per ID oder URL-String.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'URL-String oder URL-ID',
                ],
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'URL-ID (Alternative zu url)',
                ],
                'keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Keyword-Texte zum Verknüpfen',
                ],
                'keyword_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Keyword-IDs zum Verknüpfen (Alternative zu keywords)',
                ],
                'all_keywords' => [
                    'type' => 'boolean',
                    'description' => 'Alle Team-Keywords an die URL hängen',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['attach', 'detach', 'sync'],
                    'description' => 'attach (Standard): hinzufügen, detach: entfernen, sync: ersetzen',
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

            // Resolve URL
            $seoUrl = null;
            if (!empty($arguments['url_id'])) {
                $seoUrl = SeoUrl::where('team_id', $team->id)->find($arguments['url_id']);
            } elseif (!empty($arguments['url'])) {
                $hash = SeoUrl::hashUrl($arguments['url']);
                $seoUrl = SeoUrl::where('team_id', $team->id)->where('url_hash', $hash)->first();
            }

            if (!$seoUrl) {
                return ToolResult::error('URL nicht gefunden.', 'NOT_FOUND');
            }

            // Resolve keyword IDs
            $keywordIds = [];

            if (!empty($arguments['all_keywords'])) {
                $keywordIds = SeoKeyword::where('team_id', $team->id)->pluck('id')->toArray();
            } elseif (!empty($arguments['keyword_ids'])) {
                $keywordIds = SeoKeyword::where('team_id', $team->id)
                    ->whereIn('id', $arguments['keyword_ids'])
                    ->pluck('id')
                    ->toArray();
            } elseif (!empty($arguments['keywords'])) {
                foreach ($arguments['keywords'] as $kwText) {
                    $keyword = SeoKeyword::where('team_id', $team->id)
                        ->where('keyword', strtolower(trim($kwText)))
                        ->first();

                    if ($keyword) {
                        $keywordIds[] = $keyword->id;
                    }
                }
            }

            if (empty($keywordIds)) {
                return ToolResult::error('Keine passenden Keywords gefunden.', 'NO_KEYWORDS');
            }

            $action = $arguments['action'] ?? 'attach';

            switch ($action) {
                case 'detach':
                    $seoUrl->keywords()->detach($keywordIds);
                    $message = count($keywordIds) . ' Keywords von ' . $seoUrl->url . ' entfernt.';
                    break;

                case 'sync':
                    $seoUrl->keywords()->sync($keywordIds);
                    $message = count($keywordIds) . ' Keywords mit ' . $seoUrl->url . ' synchronisiert.';
                    break;

                default: // attach
                    $seoUrl->keywords()->syncWithoutDetaching($keywordIds);
                    $message = count($keywordIds) . ' Keywords an ' . $seoUrl->url . ' angehängt.';
                    break;
            }

            // Update denormalized count
            $seoUrl->update([
                'keyword_count' => $seoUrl->keywords()->count(),
                'total_search_volume' => $seoUrl->keywords()->sum('search_volume'),
            ]);

            return ToolResult::success([
                'url' => $seoUrl->url,
                'url_id' => $seoUrl->id,
                'action' => $action,
                'keyword_count' => count($keywordIds),
                'total_keywords_on_url' => $seoUrl->keywords()->count(),
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
