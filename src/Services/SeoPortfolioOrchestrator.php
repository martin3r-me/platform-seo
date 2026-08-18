<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoAnswerUnit;
use Platform\Seo\Models\SeoEntity;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoUrl;

/**
 * Kopflose Wirkungsraum-Orchestrierung — die eine Quelle der Wahrheit für Board,
 * Nachfrage-Sync, Entity-IDs und den Gesamt-Refresh. Wird sowohl vom Livewire-
 * Detail (interaktiv) als auch vom geplanten Lauf (seo:refresh-portfolios, ~2-
 * wöchentlich) genutzt — damit die Produktionslinie ohne Klick für JEDEN
 * Wirkungsraum läuft.
 */
class SeoPortfolioOrchestrator
{
    /**
     * Voller Refresh eines Wirkungsraums: Nachfrage laden → Entitäten mergen →
     * Maßnahmen erzeugen (v1 Board + v2 + KI).
     *
     * @return array{demand:int, merged:int, measures:int}
     */
    public function refresh(SeoPortfolio $portfolio): array
    {
        $memberIds = $portfolio->effectiveUrlIds();
        if (empty($memberIds)) {
            return ['demand' => 0, 'merged' => 0, 'measures' => 0];
        }
        $members = SeoUrl::whereIn('id', $memberIds)->get();

        $demand = $this->syncDemand($portfolio)['created'];

        $entityIds = $this->entityIds($portfolio->team_id, $memberIds);
        $merged = count($entityIds) >= 2
            ? (int) (app(SeoEntityMerger::class)->mergeForEntityIds($entityIds)['merged'] ?? 0)
            : 0;

        $board = $this->board($members);
        $gen = app(SeoMeasureGenerator::class);
        $n = $gen->fromBoard($portfolio, $board['rows']);
        $n += $gen->fromV2($portfolio, $memberIds);
        $ai = app(SeoMeasureAiAdvisor::class)->propose($portfolio, ['board' => $board['rows']]);
        $n += $gen->fromAi($portfolio, $ai);

        return ['demand' => $demand, 'merged' => $merged, 'measures' => $n];
    }

    /** Nachfrage-Seite: aus den Clustern des Wirkungsraums Entitäten ableiten. @return array{created:int, clusters:int} */
    public function syncDemand(SeoPortfolio $portfolio): array
    {
        $memberIds = $portfolio->effectiveUrlIds();
        $clusterIds = $this->clusterIds($memberIds);
        if (empty($clusterIds)) {
            return ['created' => 0, 'clusters' => 0];
        }

        $clusters = SeoKeywordCluster::whereIn('id', $clusterIds)->get();
        $demand = SeoKeyword::whereIn('cluster_id', $clusterIds)
            ->selectRaw('cluster_id, SUM(search_volume) as vol')
            ->groupBy('cluster_id')->pluck('vol', 'cluster_id');

        $created = 0;
        foreach ($clusters as $c) {
            $e = SeoEntity::firstOrNew(['team_id' => $portfolio->team_id, 'cluster_id' => $c->id]);
            $wasNew = ! $e->exists;
            $e->fill([
                'name' => $c->name,
                'entity_type' => 'concept',
                'search_volume' => (int) ($demand[$c->id] ?? 0),
            ])->save();
            if ($wasNew) {
                $created++;
            }
        }

        return ['created' => $created, 'clusters' => $clusters->count()];
    }

    /** @return int[] */
    public function entityIds(int $teamId, array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }
        $supply = SeoAnswerUnit::whereIn('url_id', $memberIds)->distinct()->pluck('entity_id')->all();
        $clusterIds = $this->clusterIds($memberIds);
        $demand = empty($clusterIds) ? []
            : SeoEntity::where('team_id', $teamId)->whereIn('cluster_id', $clusterIds)->pluck('id')->all();

        return collect($supply)->merge($demand)->map(fn ($i) => (int) $i)->filter()->unique()->values()->all();
    }

    /** @return int[] */
    public function clusterIds(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        return DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $memberIds)
            ->whereNotNull('k.cluster_id')
            ->distinct()->pluck('k.cluster_id')->map(fn ($i) => (int) $i)->all();
    }

    /**
     * Orchestrierungs-Board: je Cluster die rankenden Mitglieder (Kandidaten),
     * Owner, Kannibalisierungs-Konflikt, Pillar-Kandidat.
     */
    public function board(Collection $members): array
    {
        $memberIds = $members->pluck('id')->all();
        if (empty($memberIds)) {
            return ['rows' => []];
        }

        $cand = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $memberIds)
            ->whereNotNull('k.cluster_id')
            ->whereNotNull('uk.position')
            ->groupBy('k.cluster_id', 'uk.url_id')
            ->selectRaw('k.cluster_id as cluster_id, uk.url_id as url_id, MIN(uk.position) as pos')
            ->get()
            ->groupBy('cluster_id');

        if ($cand->isEmpty()) {
            return ['rows' => []];
        }

        $clusterIds = $cand->keys()->map(fn ($k) => (int) $k)->all();
        $clusters = SeoKeywordCluster::whereIn('id', $clusterIds)->get()->keyBy('id');
        $demand = SeoKeyword::whereIn('cluster_id', $clusterIds)
            ->selectRaw('cluster_id, SUM(search_volume) as vol')
            ->groupBy('cluster_id')->pluck('vol', 'cluster_id');

        $membersById = $members->keyBy('id');
        $pillarMin = (int) config('seo.pillar_candidate_min_volume', 2000);
        $rows = [];

        foreach ($clusterIds as $cid) {
            $c = $clusters[$cid] ?? null;
            if (! $c) {
                continue;
            }
            $candidates = collect($cand[$cid])->map(fn ($r) => [
                'url_id' => (int) $r->url_id,
                'label' => $membersById[$r->url_id]->display_label ?? ('#'.$r->url_id),
                'pos' => (int) $r->pos,
            ])->sortBy('pos')->values();

            $ownerId = $c->pillar_url_id ? (int) $c->pillar_url_id : null;
            $count = $candidates->count();
            $vol = (int) ($demand[$cid] ?? 0);

            $rows[] = [
                'cluster_id' => $cid,
                'name' => $c->name,
                'demand' => $vol,
                'candidates' => $candidates->all(),
                'candidate_count' => $count,
                'owner_id' => $ownerId,
                'owner_label' => $ownerId && isset($membersById[$ownerId]) ? $membersById[$ownerId]->display_label : ($ownerId ? '#'.$ownerId : null),
                'conflict' => $count >= 2 && $ownerId === null,
                'owner_not_ranking' => $ownerId !== null && ! $candidates->contains('url_id', $ownerId),
                'pillar_candidate' => $ownerId === null && $vol >= $pillarMin && $count >= 3,
            ];
        }

        usort($rows, fn ($a, $b) => $b['demand'] <=> $a['demand']);

        return ['rows' => $rows];
    }
}
