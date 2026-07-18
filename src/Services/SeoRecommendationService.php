<?php

namespace Platform\Seo\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

/**
 * Strategie-Engine (P4): leitet aus den konsolidierten Per-URL-/Cluster-Daten
 * typisierte, belegte Handlungsempfehlungen ab und persistiert sie als SeoSignal.
 *
 * Jede Empfehlung trägt Aktion + Severity + Datenbeleg (context) + Ziel. Offene
 * Empfehlungen werden nicht dupliziert. Wo der Zielknoten bekannt ist, wird die
 * Empfehlung an ihn gehängt (seo_signal) — damit sie später nach Flynk fließt.
 */
class SeoRecommendationService
{
    public const EXPAND_URL = 'rec_expand_url';
    public const BUILD_BACKLINKS = 'rec_build_backlinks';
    public const RETIRE_URL = 'rec_retire_url';
    public const CREATE_URL = 'rec_create_url';
    public const QUICK_WIN = 'rec_quick_win';

    public function __construct(
        protected SeoOrganizationLinker $linker,
    ) {}

    public function generate(SeoTeamSettings $settings): array
    {
        $teamId = $settings->team_id;

        [$expand, $backlinks] = $this->urlOptimization($teamId);

        $counts = [
            'expand_url' => $expand,
            'build_backlinks' => $backlinks,
            'retire_url' => $this->retire($teamId),
            'create_url' => $this->clusterGaps($teamId),
            'quick_win' => $this->quickWins($teamId),
        ];
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    /**
     * URLs knapp außerhalb Top-3: dünner Content → ausbauen; sonst schwaches
     * Backlink-Profil → Backlinks aufbauen. Eine Empfehlung je URL (bestes Keyword).
     */
    protected function urlOptimization(int $teamId): array
    {
        $cfg = config('seo.recommendations');

        $rows = DB::table('seo_url_keywords as uk')
            ->join('seo_urls as u', function ($join) use ($teamId) {
                $join->on('u.id', '=', 'uk.url_id')
                    ->where('u.is_own', true)
                    ->whereNull('u.deleted_at')
                    ->where('u.team_id', $teamId);
            })
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->leftJoin('seo_url_on_page as op', 'op.url_id', '=', 'u.id')
            ->whereBetween('uk.position', [$cfg['near_top_min'], $cfg['near_top_max']])
            ->where('k.search_volume', '>=', $cfg['min_volume'])
            ->select('u.id as url_id', 'u.backlink_count', 'k.id as keyword_id', 'k.keyword', 'k.search_volume', 'uk.position', 'op.word_count')
            ->orderByDesc('k.search_volume')
            ->get();

        $expand = 0;
        $backlinks = 0;

        foreach ($rows->groupBy('url_id') as $urlId => $group) {
            $best = $group->first(); // höchstes Volumen (sortiert)
            $wordCount = $best->word_count !== null ? (int) $best->word_count : null;
            $volume = (int) $best->search_volume;
            $severity = $volume >= 1000 ? 'warning' : 'watch';

            if ($wordCount === null || $wordCount < $cfg['thin_word_count']) {
                $expand += $this->persist($teamId, self::EXPAND_URL, [
                    'severity' => $severity,
                    'title' => "URL ausbauen: \"{$best->keyword}\" (Pos. {$best->position})",
                    'description' => "Rankt auf Position {$best->position} für \"{$best->keyword}\" ({$volume} Vol.), aber dünner Content"
                        .($wordCount !== null ? " ({$wordCount} Wörter)" : '').". Content vertiefen.",
                    'metric_after' => $best->position,
                    'context' => ['action' => 'expand_url', 'keyword' => $best->keyword, 'volume' => $volume, 'position' => (int) $best->position, 'word_count' => $wordCount],
                ], (int) $urlId, (int) $best->keyword_id);
            } elseif ((int) $best->backlink_count < $cfg['low_backlinks']) {
                $backlinks += $this->persist($teamId, self::BUILD_BACKLINKS, [
                    'severity' => $severity,
                    'title' => "Backlinks aufbauen: \"{$best->keyword}\" (Pos. {$best->position})",
                    'description' => "Guter Content rankt auf Position {$best->position} für \"{$best->keyword}\" ({$volume} Vol.), aber nur {$best->backlink_count} Backlinks. Linkaufbau priorisieren.",
                    'metric_after' => $best->position,
                    'context' => ['action' => 'build_backlinks', 'keyword' => $best->keyword, 'volume' => $volume, 'position' => (int) $best->position, 'backlinks' => (int) $best->backlink_count],
                ], (int) $urlId, (int) $best->keyword_id);
            }
        }

        return [$expand, $backlinks];
    }

    /**
     * Eigene URLs ohne Traffic und ohne Ranking → abstellen/zusammenführen.
     */
    protected function retire(int $teamId): int
    {
        $urls = SeoUrl::where('team_id', $teamId)
            ->where('is_own', true)
            ->where('status', 'active')
            ->where('visitors_30d', 0)
            ->whereNotNull('last_crawled_at')
            ->get(['id', 'url']);

        if ($urls->isEmpty()) {
            return 0;
        }

        $rankingUrlIds = DB::table('seo_url_keywords')
            ->whereIn('url_id', $urls->pluck('id'))
            ->whereNotNull('position')
            ->distinct()
            ->pluck('url_id')
            ->flip();

        $count = 0;
        foreach ($urls as $url) {
            if ($rankingUrlIds->has($url->id)) {
                continue;
            }
            $count += $this->persist($teamId, self::RETIRE_URL, [
                'severity' => 'watch',
                'title' => "URL abstellen: {$url->url}",
                'description' => 'Kein Traffic (30 Tage) und kein Ranking. Weiterleiten (301) oder konsolidieren.',
                'context' => ['action' => 'retire_url'],
            ], (int) $url->id);
        }

        return $count;
    }

    /**
     * Cluster mit Nachfrage, aber schwacher Abdeckung → neue URL / Content-Brief.
     */
    protected function clusterGaps(int $teamId): int
    {
        $cfg = config('seo.recommendations');

        $clusters = SeoKeywordCluster::where('team_id', $teamId)
            ->where('keyword_count', '>=', $cfg['cluster_min_keywords'])
            ->where('coverage_pct', '<', $cfg['cluster_coverage_max_pct'])
            ->get();

        $count = 0;
        foreach ($clusters as $cluster) {
            $volume = (int) SeoKeyword::where('cluster_id', $cluster->id)->sum('search_volume');
            if ($volume < $cfg['cluster_min_volume']) {
                continue;
            }

            $count += $this->persist($teamId, self::CREATE_URL, [
                'severity' => $volume >= 2000 ? 'warning' : 'watch',
                'title' => "Neue URL: Cluster \"{$cluster->name}\" ({$cluster->coverage_pct}% abgedeckt)",
                'description' => "Cluster hat {$cluster->keyword_count} Keywords ({$volume} Vol.), aber nur {$cluster->coverage_pct}% Abdeckung. Content-Brief anlegen.",
                'context' => [
                    'action' => 'create_url',
                    'cluster_id' => (int) $cluster->id,
                    'cluster' => $cluster->name,
                    'coverage_pct' => (float) $cluster->coverage_pct,
                    'keyword_count' => (int) $cluster->keyword_count,
                    'volume' => $volume,
                ],
            ]);
        }

        return $count;
    }

    /**
     * Keywords mit niedriger Difficulty, relevantem Volumen und schwacher/keiner
     * eigenen Position → Quick Win.
     */
    protected function quickWins(int $teamId): int
    {
        $cfg = config('seo.recommendations');

        $keywords = SeoKeyword::where('team_id', $teamId)
            ->whereNotNull('keyword_difficulty')
            ->where('keyword_difficulty', '<=', $cfg['quick_win_max_difficulty'])
            ->where('search_volume', '>=', $cfg['quick_win_min_volume'])
            ->orderByDesc('search_volume')
            ->limit(50)
            ->get(['id', 'keyword', 'search_volume', 'keyword_difficulty']);

        if ($keywords->isEmpty()) {
            return 0;
        }

        $bestPosition = DB::table('seo_url_keywords as uk')
            ->join('seo_urls as u', function ($join) use ($teamId) {
                $join->on('u.id', '=', 'uk.url_id')
                    ->where('u.is_own', true)
                    ->whereNull('u.deleted_at')
                    ->where('u.team_id', $teamId);
            })
            ->whereIn('uk.keyword_id', $keywords->pluck('id'))
            ->whereNotNull('uk.position')
            ->groupBy('uk.keyword_id')
            ->select('uk.keyword_id', DB::raw('MIN(uk.position) as best'))
            ->pluck('best', 'uk.keyword_id');

        $count = 0;
        foreach ($keywords as $keyword) {
            $pos = $bestPosition[$keyword->id] ?? null;
            if ($pos !== null && (int) $pos <= $cfg['quick_win_weak_position']) {
                continue; // rankt bereits ordentlich
            }

            $posText = $pos !== null ? "Position {$pos}" : 'keine eigene Position';
            $count += $this->persist($teamId, self::QUICK_WIN, [
                'severity' => 'info',
                'title' => "Quick Win: \"{$keyword->keyword}\" (KD {$keyword->keyword_difficulty})",
                'description' => "Niedrige Difficulty ({$keyword->keyword_difficulty}), {$keyword->search_volume} Vol., {$posText}. Schnell erreichbares Ranking.",
                'metric_after' => $keyword->search_volume,
                'context' => ['action' => 'quick_win', 'keyword' => $keyword->keyword, 'volume' => (int) $keyword->search_volume, 'difficulty' => (int) $keyword->keyword_difficulty],
            ], null, (int) $keyword->id);
        }

        return $count;
    }

    /**
     * Persistiert eine Empfehlung als SeoSignal — dedupliziert gegen offene
     * Empfehlungen desselben Typs & Ziels und hängt sie best-effort an den Knoten.
     */
    protected function persist(int $teamId, string $type, array $data, ?int $urlId = null, ?int $keywordId = null): int
    {
        $clusterId = $data['context']['cluster_id'] ?? null;

        $exists = SeoSignal::where('team_id', $teamId)
            ->where('signal_type', $type)
            ->whereIn('status', ['new', 'acknowledged'])
            ->when($urlId !== null, fn ($q) => $q->where('url_id', $urlId))
            ->when($urlId === null && $keywordId !== null, fn ($q) => $q->where('keyword_id', $keywordId))
            ->when($urlId === null && $keywordId === null && $clusterId !== null, fn ($q) => $q->where('context->cluster_id', $clusterId))
            ->exists();

        if ($exists) {
            return 0;
        }

        $signal = SeoSignal::create(array_merge([
            'team_id' => $teamId,
            'signal_type' => $type,
            'url_id' => $urlId,
            'keyword_id' => $keywordId,
            'detected_at' => Carbon::today(),
            'status' => 'new',
        ], $data));

        $this->linkToNode($signal, $urlId, $keywordId, $clusterId);

        return 1;
    }

    /**
     * Hängt die Empfehlung an den Knoten ihres Ziels (best-effort; No-Op, solange
     * noch keine Knoten-Links bestehen).
     */
    protected function linkToNode(SeoSignal $signal, ?int $urlId, ?int $keywordId, ?int $clusterId): void
    {
        $nodeIds = [];

        if ($urlId !== null) {
            $nodeIds = $this->linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_URL, $urlId);
        } elseif ($clusterId !== null) {
            $nodeIds = $this->linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_CLUSTER, $clusterId);
        } elseif ($keywordId !== null) {
            $cid = SeoKeyword::whereKey($keywordId)->value('cluster_id');
            if ($cid) {
                $nodeIds = $this->linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_CLUSTER, (int) $cid);
            }
        }

        if (! empty($nodeIds)) {
            $this->linker->setNode(SeoOrganizationLinker::ALIAS_SIGNAL, $signal->id, $nodeIds[0]);
        }
    }
}
