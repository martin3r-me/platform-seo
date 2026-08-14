<?php

namespace Platform\Seo\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoSignalDefinition;
use Platform\Seo\Models\SeoUrl;

/**
 * Definition-getriebener Signal-Evaluator (docs/SIGNALS-CONCEPT.md).
 *
 * GOVERNANCE: nicht jeder Treffer wird ein Signal. Kandidaten werden gesammelt,
 * nach Impact gereiht und nur bis zum WIP-Limit (max. offene) + Tageslimit (max.
 * neue/Tag) zugelassen. Neue Signale kommen erst nach, wenn offene erledigt sind —
 * ein kleiner, bearbeitbarer Arbeitsvorrat statt einer Flut.
 */
class SeoSignalEvaluator
{
    public function __construct(private SeoOrganizationLinker $linker) {}

    /**
     * @return array{definitions:int, candidates:int, open_now:int, slots:int, admitted:int, by_pattern:array<string,int>}
     */
    public function evaluateTeam(int $teamId, ?string $frequency = null): array
    {
        $defs = SeoSignalDefinition::where('team_id', $teamId)
            ->where('is_active', true)
            ->when($frequency, fn ($q) => $q->where('frequency', $frequency))
            ->get();

        // 1. Kandidaten aus allen Definitionen sammeln (noch nicht persistieren).
        $candidates = [];
        foreach ($defs as $def) {
            $urlIds = $this->populationUrlIds($def);
            if (empty($urlIds)) {
                continue;
            }
            foreach ($this->candidatesFor($def, $urlIds) as $cand) {
                // Bereits offene (gleiche Definition + Ziel) zählen nicht als neuer Bedarf.
                if ($this->alreadyOpen($def->id, $cand['url_id'], $cand['keyword_id'])) {
                    continue;
                }
                $candidates[] = $cand;
            }
        }

        // 2. Nach Wert (Impact) reihen — das Wertvollste zuerst.
        usort($candidates, fn ($a, $b) => ($b['impact'] ?? 0) <=> ($a['impact'] ?? 0));

        // 3. Freie Plätze = min(WIP-Rest, Tages-Rest). Nur so viele dürfen rein.
        $wip = (int) config('seo.signals.wip_limit', 5);
        $daily = (int) config('seo.signals.daily_new_limit', 3);
        $openNow = $this->openCount($teamId);
        $todayNew = $this->admittedToday($teamId);
        $slots = max(0, min($wip - $openNow, $daily - $todayNew));

        // 4. Nur die Top-Kandidaten zulassen.
        $admitted = array_slice($candidates, 0, $slots);
        $created = 0;
        $byPattern = [];
        foreach ($admitted as $cand) {
            if ($this->persist($cand['def'], $cand['data'], $cand['url_id'], $cand['keyword_id'])) {
                $created++;
                $p = $cand['def']->pattern_type;
                $byPattern[$p] = ($byPattern[$p] ?? 0) + 1;
            }
        }

        return [
            'definitions' => $defs->count(),
            'candidates' => count($candidates),
            'open_now' => $openNow,
            'slots' => $slots,
            'admitted' => $created,
            'by_pattern' => $byPattern,
        ];
    }

    // -------------------------------------------------------------------------
    // Governance-Zähler
    // -------------------------------------------------------------------------

    /** Offene definition-getriebene Signale (WIP-Zähler). */
    protected function openCount(int $teamId): int
    {
        return SeoSignal::where('team_id', $teamId)
            ->whereNotNull('signal_definition_id')
            ->whereIn('status', ['new', 'acknowledged'])
            ->count();
    }

    /** Heute bereits zugelassene definition-getriebene Signale (Tageslimit). */
    protected function admittedToday(int $teamId): int
    {
        return SeoSignal::where('team_id', $teamId)
            ->whereNotNull('signal_definition_id')
            ->whereDate('detected_at', Carbon::today())
            ->count();
    }

    protected function alreadyOpen(int $defId, ?int $urlId, ?int $keywordId): bool
    {
        return SeoSignal::where('signal_definition_id', $defId)
            ->whereIn('status', ['new', 'acknowledged'])
            ->when($urlId !== null, fn ($q) => $q->where('url_id', $urlId))
            ->when($urlId === null && $keywordId !== null, fn ($q) => $q->where('keyword_id', $keywordId))
            ->exists();
    }

    // -------------------------------------------------------------------------
    // Population (eine pro Definition)
    // -------------------------------------------------------------------------

    /** @return int[] eigene, aktive URL-IDs der Population dieser Definition */
    protected function populationUrlIds(SeoSignalDefinition $def): array
    {
        $base = SeoUrl::where('team_id', $def->team_id)
            ->where('is_own', true)
            ->where('status', 'active');

        switch ($def->scope_type) {
            case 'list':
                $listId = $def->scope_value['list_id'] ?? null;
                if (! $listId) {
                    return [];
                }
                $ids = DB::table('seo_url_list_entries')->where('list_id', $listId)->pluck('url_id')->all();

                return empty($ids) ? [] : $base->whereIn('id', $ids)->pluck('id')->map(fn ($i) => (int) $i)->all();

            case 'entity':
            case 'entity_subtree':
                $eid = (int) ($def->scope_value['entity_id'] ?? 0);
                if (! $eid) {
                    return [];
                }
                $nodes = $def->scope_type === 'entity_subtree'
                    ? $this->linker->descendantEntityIds($eid)
                    : [$eid];
                $ids = $this->linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $nodes);

                return empty($ids) ? [] : $base->whereIn('id', $ids)->pluck('id')->map(fn ($i) => (int) $i)->all();

            case 'all':
            default:
                return $base->pluck('id')->map(fn ($i) => (int) $i)->all();
        }
    }

    // -------------------------------------------------------------------------
    // Kandidaten je Muster (NICHT persistieren — nur vorschlagen)
    // -------------------------------------------------------------------------

    /** @return array<int, array{def:SeoSignalDefinition, url_id:?int, keyword_id:?int, impact:int, data:array}> */
    protected function candidatesFor(SeoSignalDefinition $def, array $urlIds): array
    {
        return match ($def->pattern_type) {
            'striking_distance' => $this->strikingDistance($def, $urlIds),
            'position_drop' => $this->positionDrop($def, $urlIds),
            'thin_content' => $this->thinContent($def, $urlIds),
            'cannibalization' => $this->cannibalization($def, $urlIds),
            default => [],
        };
    }

    protected function strikingDistance(SeoSignalDefinition $def, array $urlIds): array
    {
        $c = $def->conditions ?? [];
        $minPos = (int) ($c['min_position'] ?? 4);
        $maxPos = (int) ($c['max_position'] ?? 10);
        $minVol = (int) ($c['min_volume'] ?? 100);

        $rows = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $urlIds)
            ->whereBetween('uk.position', [$minPos, $maxPos])
            ->where('k.search_volume', '>=', $minVol)
            ->select('uk.url_id', 'uk.keyword_id', 'uk.position', 'k.keyword', 'k.search_volume')
            ->orderByDesc('k.search_volume')
            ->get();

        $out = [];
        foreach ($this->bestPerUrl($rows) as $r) {
            $impact = (int) round($r->search_volume * (11 - $r->position) / 10);
            $out[] = [
                'def' => $def,
                'url_id' => (int) $r->url_id,
                'keyword_id' => (int) $r->keyword_id,
                'impact' => $impact,
                'data' => [
                    'title' => "Griffweite: \"{$r->keyword}\" auf Pos. {$r->position} — ausbauen",
                    'description' => "Rankt auf Position {$r->position} für \"{$r->keyword}\" ({$r->search_volume} Vol.). Knapp außerhalb Top-3 — der klarste Ausbau-Hebel.",
                    'metric_after' => $r->position,
                    'context' => ['pattern' => 'striking_distance', 'keyword' => $r->keyword, 'volume' => (int) $r->search_volume, 'position' => (int) $r->position, 'impact' => $impact],
                ],
            ];
        }

        return $out;
    }

    protected function positionDrop(SeoSignalDefinition $def, array $urlIds): array
    {
        $c = $def->conditions ?? [];
        $minDrop = (int) ($c['min_drop'] ?? 3);
        $minVol = (int) ($c['min_volume'] ?? 50);

        $rows = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $urlIds)
            ->whereNotNull('uk.position')
            ->whereNotNull('uk.previous_position')
            ->where('k.search_volume', '>=', $minVol)
            ->select('uk.url_id', 'uk.keyword_id', 'uk.position', 'uk.previous_position', 'k.keyword', 'k.search_volume')
            ->get()
            ->map(function ($r) {
                $r->drop = (int) $r->position - (int) $r->previous_position; // positiv = schlechter geworden
                return $r;
            })
            ->filter(fn ($r) => $r->drop >= $minDrop)
            ->sortByDesc('drop')
            ->values();

        $out = [];
        foreach ($this->bestPerUrl($rows) as $r) {
            $impact = (int) round($r->search_volume * $r->drop);
            $out[] = [
                'def' => $def,
                'url_id' => (int) $r->url_id,
                'keyword_id' => (int) $r->keyword_id,
                'impact' => $impact,
                'data' => [
                    'title' => "Position gefallen: \"{$r->keyword}\" {$r->previous_position}→{$r->position}",
                    'description' => "Für \"{$r->keyword}\" ({$r->search_volume} Vol.) von {$r->previous_position} auf {$r->position} abgerutscht. Ursache prüfen.",
                    'metric_before' => $r->previous_position,
                    'metric_after' => $r->position,
                    'metric_delta' => $r->drop,
                    'context' => ['pattern' => 'position_drop', 'keyword' => $r->keyword, 'volume' => (int) $r->search_volume, 'drop' => (int) $r->drop, 'impact' => $impact],
                ],
            ];
        }

        return $out;
    }

    protected function thinContent(SeoSignalDefinition $def, array $urlIds): array
    {
        $c = $def->conditions ?? [];
        $thin = (int) ($c['thin_word_count'] ?? 300);
        $maxPos = (int) ($c['max_position'] ?? 20);
        $minVol = (int) ($c['min_volume'] ?? 50);

        $rows = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->leftJoin('seo_url_on_page as op', 'op.url_id', '=', 'uk.url_id')
            ->whereIn('uk.url_id', $urlIds)
            ->whereNotNull('uk.position')
            ->where('uk.position', '<=', $maxPos)
            ->where('k.search_volume', '>=', $minVol)
            ->where(function ($q) use ($thin) {
                $q->whereNull('op.word_count')->orWhere('op.word_count', '<', $thin);
            })
            ->select('uk.url_id', 'uk.keyword_id', 'uk.position', 'k.keyword', 'k.search_volume', 'op.word_count')
            ->orderByDesc('k.search_volume')
            ->get();

        $out = [];
        foreach ($this->bestPerUrl($rows) as $r) {
            $wc = $r->word_count !== null ? (int) $r->word_count : null;
            $impact = (int) round($r->search_volume * (21 - min((int) $r->position, 20)) / 20);
            $out[] = [
                'def' => $def,
                'url_id' => (int) $r->url_id,
                'keyword_id' => (int) $r->keyword_id,
                'impact' => $impact,
                'data' => [
                    'title' => "Dünner Content: \"{$r->keyword}\" (Pos. {$r->position}".($wc !== null ? ", {$wc} W." : '').')',
                    'description' => "Rankt für \"{$r->keyword}\" ({$r->search_volume} Vol.) auf Pos. {$r->position}, aber zu wenig Inhalt. Content-Brief erstellen.",
                    'metric_after' => $r->position,
                    'context' => ['pattern' => 'thin_content', 'keyword' => $r->keyword, 'volume' => (int) $r->search_volume, 'position' => (int) $r->position, 'word_count' => $wc, 'impact' => $impact],
                ],
            ];
        }

        return $out;
    }

    /** Relational — Population = Arena. Eine Kandidatur je Keyword. */
    protected function cannibalization(SeoSignalDefinition $def, array $urlIds): array
    {
        $c = $def->conditions ?? [];
        $minUrls = (int) ($c['min_urls'] ?? 2);
        $minVol = (int) ($c['min_volume'] ?? 0);

        $rows = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $urlIds)
            ->whereNotNull('uk.position')
            ->where('k.search_volume', '>=', $minVol)
            ->select('uk.url_id', 'uk.keyword_id', 'uk.position', 'k.keyword', 'k.search_volume')
            ->get()
            ->groupBy('keyword_id');

        $out = [];
        foreach ($rows as $keywordId => $group) {
            $urls = $group->unique('url_id');
            if ($urls->count() < $minUrls) {
                continue;
            }
            $first = $group->first();
            $vol = (int) $first->search_volume;
            $impact = $vol * ($urls->count() - 1);
            $competing = $urls->sortBy('position')->map(fn ($r) => ['url_id' => (int) $r->url_id, 'position' => (int) $r->position])->values()->all();

            $out[] = [
                'def' => $def,
                'url_id' => null,
                'keyword_id' => (int) $keywordId,
                'impact' => $impact,
                'data' => [
                    'title' => "Kannibalisierung: \"{$first->keyword}\" — {$urls->count()} eigene URLs",
                    'description' => "{$urls->count()} eigene Seiten der Arena ranken für \"{$first->keyword}\" ({$vol} Vol.). Konsolidieren oder entflechten.",
                    'context' => ['pattern' => 'cannibalization', 'keyword' => $first->keyword, 'volume' => $vol, 'url_count' => $urls->count(), 'urls' => $competing, 'scope' => $def->scope_value, 'impact' => $impact],
                ],
            ];
        }

        return $out;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /** Behält je URL nur den ersten (= besten, da vorsortierten) Treffer. */
    protected function bestPerUrl($rows)
    {
        $seen = [];
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r->url_id;
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $r;
        }

        return $out;
    }

    /** Erzeugt ein Signal — dedupt auf (Definition, Ziel), heftet es an den Knoten. */
    protected function persist(SeoSignalDefinition $def, array $data, ?int $urlId, ?int $keywordId): int
    {
        $exists = SeoSignal::where('signal_definition_id', $def->id)
            ->whereIn('status', ['new', 'acknowledged'])
            ->when($urlId !== null, fn ($q) => $q->where('url_id', $urlId))
            ->when($urlId === null && $keywordId !== null, fn ($q) => $q->where('keyword_id', $keywordId))
            ->exists();

        if ($exists) {
            return 0;
        }

        $signal = SeoSignal::create(array_merge([
            'team_id' => $def->team_id,
            'signal_definition_id' => $def->id,
            'signal_type' => $def->pattern_type,
            'severity' => $def->severity,
            'url_id' => $urlId,
            'keyword_id' => $keywordId,
            'detected_at' => Carbon::today(),
            'status' => 'new',
        ], $data));

        $this->linkToNode($signal, $urlId, $keywordId);

        return 1;
    }

    /** Best-effort: hängt das Signal an den Org-Knoten seines Ziels. */
    protected function linkToNode(SeoSignal $signal, ?int $urlId, ?int $keywordId): void
    {
        $nodeIds = [];
        if ($urlId !== null) {
            $nodeIds = $this->linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_URL, $urlId);
        } elseif ($keywordId !== null) {
            $cid = \Platform\Seo\Models\SeoKeyword::whereKey($keywordId)->value('cluster_id');
            if ($cid) {
                $nodeIds = $this->linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_CLUSTER, (int) $cid);
            }
        }

        if (! empty($nodeIds)) {
            $this->linker->setNode(SeoOrganizationLinker::ALIAS_SIGNAL, $signal->id, $nodeIds[0]);
        }
    }
}
