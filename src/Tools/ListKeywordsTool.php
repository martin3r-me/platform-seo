<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoKeyword;

class ListKeywordsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/keywords - Listet Keywords des Teams. Optional: search, cluster_id, search_intent (informational/navigational/commercial/transactional), min_volume, max_volume, has_position (true/false), sort (search_volume/keyword_difficulty/position), sort_dir, limit, offset. keyword_id für Detail.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'keyword_id' => [
                    'type' => 'integer',
                    'description' => 'Detail eines Keywords mit Positionen, URLs, Trend-Daten',
                ],
                'search' => ['type' => 'string'],
                'cluster_id' => ['type' => 'integer'],
                'search_intent' => [
                    'type' => 'string',
                    'enum' => ['informational', 'navigational', 'commercial', 'transactional'],
                ],
                'min_volume' => ['type' => 'integer'],
                'max_volume' => ['type' => 'integer'],
                'has_position' => [
                    'type' => 'boolean',
                    'description' => 'true: nur Keywords mit Ranking, false: nur ohne',
                ],
                'sort' => [
                    'type' => 'string',
                    'enum' => ['search_volume', 'keyword_difficulty', 'keyword', 'competition'],
                ],
                'sort_dir' => [
                    'type' => 'string',
                    'enum' => ['asc', 'desc'],
                ],
                'limit' => ['type' => 'integer'],
                'offset' => ['type' => 'integer'],
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

            // Detail mode
            if (!empty($arguments['keyword_id'])) {
                return $this->getDetail((int) $arguments['keyword_id'], $team->id);
            }

            $query = SeoKeyword::where('team_id', $team->id);

            if (!empty($arguments['search'])) {
                $query->where('keyword', 'like', '%' . $arguments['search'] . '%');
            }
            if (!empty($arguments['cluster_id'])) {
                $query->where('cluster_id', (int) $arguments['cluster_id']);
            }
            if (!empty($arguments['search_intent'])) {
                $query->where('search_intent', $arguments['search_intent']);
            }
            if (isset($arguments['min_volume'])) {
                $query->where('search_volume', '>=', (int) $arguments['min_volume']);
            }
            if (isset($arguments['max_volume'])) {
                $query->where('search_volume', '<=', (int) $arguments['max_volume']);
            }
            if (isset($arguments['has_position'])) {
                if ($arguments['has_position']) {
                    $query->whereHas('urls', fn ($q) => $q->whereNotNull('seo_url_keywords.position'));
                } else {
                    $query->whereDoesntHave('urls', fn ($q) => $q->whereNotNull('seo_url_keywords.position'));
                }
            }

            $sort = $arguments['sort'] ?? 'search_volume';
            $dir = $arguments['sort_dir'] ?? 'desc';
            $query->orderBy($sort, $dir);

            $limit = min((int) ($arguments['limit'] ?? 50), 200);
            $offset = (int) ($arguments['offset'] ?? 0);
            $total = $query->count();

            $keywords = $query->with('cluster')->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'keywords' => $keywords->map(fn (SeoKeyword $kw) => [
                    'id' => $kw->id,
                    'keyword' => $kw->keyword,
                    'search_volume' => $kw->search_volume,
                    'keyword_difficulty' => $kw->keyword_difficulty,
                    'competition' => $kw->competition ? (float) $kw->competition : null,
                    'search_intent' => $kw->search_intent,
                    'cpc_euro' => $kw->cpc_euro,
                    'cluster' => $kw->cluster?->name,
                    'cluster_id' => $kw->cluster_id,
                    'topic' => $kw->topic,
                    'last_fetched_at' => $kw->last_fetched_at?->toIso8601String(),
                ])->all(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function getDetail(int $keywordId, int $teamId): ToolResult
    {
        $kw = SeoKeyword::with(['cluster', 'urls', 'positions' => fn ($q) => $q->limit(30)])
            ->where('team_id', $teamId)
            ->find($keywordId);

        if (!$kw) {
            return ToolResult::error('Keyword nicht gefunden.', 'NOT_FOUND');
        }

        return ToolResult::success([
            'keyword' => [
                'id' => $kw->id,
                'uuid' => $kw->uuid,
                'keyword' => $kw->keyword,
                'search_volume' => $kw->search_volume,
                'keyword_difficulty' => $kw->keyword_difficulty,
                'competition' => $kw->competition ? (float) $kw->competition : null,
                'search_intent' => $kw->search_intent,
                'cpc_euro' => $kw->cpc_euro,
                'topic' => $kw->topic,
                'monthly_volumes' => $kw->monthly_volumes,
                'median_volume' => $kw->median_volume,
                'min_volume' => $kw->min_volume,
                'max_volume' => $kw->max_volume,
                'trends_sparkline' => $kw->trends_sparkline,
                'cluster' => $kw->cluster?->name,
                'cluster_id' => $kw->cluster_id,
                'last_fetched_at' => $kw->last_fetched_at?->toIso8601String(),
            ],
            'urls' => $kw->urls->map(fn ($u) => [
                'id' => $u->id,
                'url' => $u->url,
                'position' => $u->pivot->position,
                'previous_position' => $u->pivot->previous_position,
            ])->all(),
            'position_history' => $kw->positions->map(fn ($p) => [
                'position' => $p->position,
                'previous_position' => $p->previous_position,
                'ranked_url' => $p->ranked_url,
                'tracked_at' => $p->tracked_at?->toIso8601String(),
                'delta' => $p->position_delta,
            ])->all(),
        ]);
    }
}
