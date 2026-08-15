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
        return 'GET /seo/keywords - Listet Keywords des Teams. Optional: search, domain (nur Keywords, für die eine URL dieser Domain rankt), url_id (Keywords einer bestimmten URL), cluster_id, search_intent (informational/navigational/commercial/transactional), min_volume, max_volume, has_position (true/false), sort (search_volume/keyword_difficulty/keyword/competition/cpc_cents/last_fetched_at), sort_dir (asc/desc), limit, offset. keyword_id für Detail.';
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
                'domain' => [
                    'type' => 'string',
                    'description' => 'Nur Keywords, für die eine URL dieser Domain rankt (z.B. "tm-foodsolutions.de"). Schema/www werden ignoriert; Subdomains sind eingeschlossen.',
                ],
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords, die mit dieser URL verknüpft sind.',
                ],
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
                    'enum' => ['search_volume', 'keyword_difficulty', 'keyword', 'competition', 'cpc_cents', 'last_fetched_at'],
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
            $domain = $this->normalizeDomain($arguments['domain'] ?? null);
            if ($domain !== null) {
                $query->whereHas('urls', function ($q) use ($domain) {
                    $q->where('seo_urls.domain', $domain)
                        ->orWhere('seo_urls.domain', 'like', '%.' . $domain);
                });
            }
            if (!empty($arguments['url_id'])) {
                $urlId = (int) $arguments['url_id'];
                $query->whereHas('urls', fn ($q) => $q->where('seo_urls.id', $urlId));
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

            [$sort, $dir] = $this->normalizeSort($arguments['sort'] ?? null, $arguments['sort_dir'] ?? null);
            $query->orderBy($sort, $dir);

            $limit = min((int) ($arguments['limit'] ?? 50), 200);
            $offset = (int) ($arguments['offset'] ?? 0);
            $total = $query->count();

            $keywords = $query->with(['cluster', 'urls'])->skip($offset)->take($limit)->get();

            $urlIdFilter = !empty($arguments['url_id']) ? (int) $arguments['url_id'] : null;

            return ToolResult::success([
                'keywords' => $keywords->map(function (SeoKeyword $kw) use ($domain, $urlIdFilter) {
                    // Beste Position bevorzugt aus den gefilterten URLs (Domain/url_id),
                    // damit die gemeldete Position zur Anfrage passt und nicht zu einer Fremd-Domain.
                    $urls = $kw->urls;
                    if ($domain !== null) {
                        $scoped = $urls->filter(fn ($u) => $u->domain === $domain || str_ends_with((string) $u->domain, '.' . $domain));
                        if ($scoped->isNotEmpty()) {
                            $urls = $scoped;
                        }
                    }
                    if ($urlIdFilter !== null) {
                        $scoped = $urls->filter(fn ($u) => (int) $u->id === $urlIdFilter);
                        if ($scoped->isNotEmpty()) {
                            $urls = $scoped;
                        }
                    }
                    // Beste (niedrigste) Position; URLs ohne Position ans Ende.
                    $bestUrl = $urls->sortBy(fn ($u) => $u->pivot?->position ?? PHP_INT_MAX)->first();

                    $result = [
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
                        'position' => $bestUrl?->pivot?->position,
                        'previous_position' => $bestUrl?->pivot?->previous_position,
                        'ranked_url' => $bestUrl?->url,
                        'last_fetched_at' => $kw->last_fetched_at?->toIso8601String(),
                    ];

                    return $result;
                })->all(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    /**
     * Normalisiert eine Domain-Eingabe: entfernt Schema, Pfad und führendes "www.".
     * Gibt null zurück, wenn nichts Sinnvolles übrig bleibt.
     */
    private function normalizeDomain(mixed $input): ?string
    {
        if (!is_string($input) || trim($input) === '') {
            return null;
        }
        $host = strtolower(trim($input));
        $host = preg_replace('#^[a-z]+://#', '', $host);   // Schema weg
        $host = explode('/', $host, 2)[0];                  // Pfad weg
        $host = preg_replace('#^www\.#', '', $host);        // führendes www weg
        $host = trim($host);

        return $host !== '' ? $host : null;
    }

    /**
     * Robuste Sort-Normalisierung. Akzeptiert:
     *  - String "search_volume"
     *  - String "search_volume:desc"
     *  - Array [{"field":"search_volume","dir":"desc"}] oder ["search_volume"]
     * und fällt bei ungültiger Spalte sicher auf search_volume/desc zurück
     * (verhindert SQL-Fehler und Spalten-Injection via orderBy).
     *
     * @return array{0:string,1:string}
     */
    private function normalizeSort(mixed $sort, mixed $sortDir): array
    {
        $allowed = ['search_volume', 'keyword_difficulty', 'keyword', 'competition', 'cpc_cents', 'last_fetched_at'];
        $dir = strtolower((string) ($sortDir ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        // Array-Form von MCP-Clients defensiv entpacken
        if (is_array($sort)) {
            $first = $sort[0] ?? null;
            if (is_array($first)) {
                $dir = strtolower((string) ($first['dir'] ?? $dir)) === 'asc' ? 'asc' : 'desc';
                $sort = $first['field'] ?? null;
            } else {
                $sort = $first;
            }
        }

        $sort = is_string($sort) ? trim($sort) : '';
        // "field:dir"-Kurzform
        if (str_contains($sort, ':')) {
            [$field, $maybeDir] = explode(':', $sort, 2);
            $sort = trim($field);
            if ($maybeDir !== '') {
                $dir = strtolower(trim($maybeDir)) === 'asc' ? 'asc' : 'desc';
            }
        }

        $sort = in_array($sort, $allowed, true) ? $sort : 'search_volume';

        return [$sort, $dir];
    }

    private function getDetail(int $keywordId, int $teamId): ToolResult
    {
        $kw = SeoKeyword::with(['cluster', 'urls', 'positions' => fn ($q) => $q->limit(30), 'serp'])
            ->where('team_id', $teamId)
            ->find($keywordId);

        if (!$kw) {
            return ToolResult::error('Keyword nicht gefunden.', 'NOT_FOUND');
        }

        return ToolResult::success([
            'serp_features' => $kw->serp ? [
                'item_types' => $kw->serp->item_types,
                'people_also_ask' => $kw->serp->people_also_ask,
                'related_searches' => $kw->serp->related_searches,
                'featured_snippet' => $kw->serp->featured_snippet,
                'has_ai_overview' => $kw->serp->has_ai_overview,
                'ai_overview_references' => $kw->serp->ai_overview_references,
                'fetched_at' => $kw->serp->fetched_at?->toIso8601String(),
            ] : null,
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
