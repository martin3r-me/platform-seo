<?php

namespace Platform\Seo\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoSignalDefinition;
use Platform\Seo\Models\SeoUrl;

/**
 * Definition-getriebener Signal-Evaluator (Schritt 2, docs/SIGNALS-CONCEPT.md).
 *
 * Läuft die aktiven SeoSignalDefinitions über ihre Population und erzeugt daraus
 * echte seo_signals — verknüpft mit ihrer Definition, angeheftet ans richtige Ziel.
 * Eine Population pro Definition; bei relationalen Mustern ist die Population die Arena.
 */
class SeoSignalEvaluator
{
    public function __construct(private SeoOrganizationLinker $linker) {}

    /**
     * Alle (optional nach Frequenz gefilterten) aktiven Definitionen eines Teams auswerten.
     *
     * @return array{definitions:int, created:int, by_pattern:array<string,int>}
     */
    public function evaluateTeam(int $teamId, ?string $frequency = null): array
    {
        $defs = SeoSignalDefinition::where('team_id', $teamId)
            ->where('is_active', true)
            ->when($frequency, fn ($q) => $q->where('frequency', $frequency))
            ->get();

        $created = 0;
        $byPattern = [];
        foreach ($defs as $def) {
            $n = $this->evaluateDefinition($def);
            $created += $n;
            $byPattern[$def->pattern_type] = ($byPattern[$def->pattern_type] ?? 0) + $n;
        }

        return ['definitions' => $defs->count(), 'created' => $created, 'by_pattern' => $byPattern];
    }

    public function evaluateDefinition(SeoSignalDefinition $def): int
    {
        $urlIds = $this->populationUrlIds($def);
        if (empty($urlIds)) {
            return 0;
        }

        return match ($def->pattern_type) {
            'striking_distance' => $this->strikingDistance($def, $urlIds),
            'position_drop' => $this->positionDrop($def, $urlIds),
            'thin_content' => $this->thinContent($def, $urlIds),
            'cannibalization' => $this->cannibalization($def, $urlIds),
            default => 0, // weitere Muster: spätere Ausbaustufe
        };
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
    // Muster (pro-URL)
    // -------------------------------------------------------------------------

    /** Position 4–10 für nachgefragtes Keyword → Ausbau-Hebel. Eine je URL (bestes KW). */
    protected function strikingDistance(SeoSignalDefinition $def, array $urlIds): int
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

        $created = 0;
        foreach ($this->bestPerUrl($rows) as $r) {
            $impact = (int) round($r->search_volume * (11 - $r->position) / 10);
            $created += $this->persist($def, [
                'title' => "Griffweite: \"{$r->keyword}\" auf Pos. {$r->position} — ausbauen",
                'description' => "Rankt auf Position {$r->position} für \"{$r->keyword}\" ({$r->search_volume} Vol.). Knapp außerhalb Top-3 — der klarste Ausbau-Hebel.",
                'metric_after' => $r->position,
                'context' => ['pattern' => 'striking_distance', 'keyword' => $r->keyword, 'volume' => (int) $r->search_volume, 'position' => (int) $r->position, 'impact' => $impact],
            ], (int) $r->url_id, (int) $r->keyword_id);
        }

        return $created;
    }

    /** Ranking über Snapshots deutlich abgerutscht. Eine je URL (größter Abfall). */
    protected function positionDrop(SeoSignalDefinition $def, array $urlIds): int
    {
        $c = $def->conditions ?? [];
        $minDrop = (int) ($c['min_drop'] ?? 3);
        $minVol = (int) ($c['min_volume'] ?? 50);

        // UNSIGNED-Spalten: Delta in PHP rechnen, nicht in SQL (Overflow-Falle).
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

        $created = 0;
        foreach ($this->bestPerUrl($rows) as $r) {
            $impact = (int) round($r->search_volume * $r->drop);
            $created += $this->persist($def, [
                'title' => "Position gefallen: \"{$r->keyword}\" {$r->previous_position}→{$r->position}",
                'description' => "Für \"{$r->keyword}\" ({$r->search_volume} Vol.) von {$r->previous_position} auf {$r->position} abgerutscht. Ursache prüfen.",
                'metric_before' => $r->previous_position,
                'metric_after' => $r->position,
                'metric_delta' => $r->drop,
                'context' => ['pattern' => 'position_drop', 'keyword' => $r->keyword, 'volume' => (int) $r->search_volume, 'drop' => (int) $r->drop, 'impact' => $impact],
            ], (int) $r->url_id, (int) $r->keyword_id);
        }

        return $created;
    }

    /** Rankende URL mit zu dünnem Content → Content-Brief-Kandidat. Eine je URL. */
    protected function thinContent(SeoSignalDefinition $def, array $urlIds): int
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

        $created = 0;
        foreach ($this->bestPerUrl($rows) as $r) {
            $wc = $r->word_count !== null ? (int) $r->word_count : null;
            $impact = (int) round($r->search_volume * (21 - min((int) $r->position, 20)) / 20);
            $created += $this->persist($def, [
                'title' => "Dünner Content: \"{$r->keyword}\" (Pos. {$r->position}".($wc !== null ? ", {$wc} W." : '').')',
                'description' => "Rankt für \"{$r->keyword}\" ({$r->search_volume} Vol.) auf Pos. {$r->position}, aber zu wenig Inhalt. Content-Brief erstellen.",
                'metric_after' => $r->position,
                'context' => ['pattern' => 'thin_content', 'keyword' => $r->keyword, 'volume' => (int) $r->search_volume, 'position' => (int) $r->position, 'word_count' => $wc, 'impact' => $impact],
            ], (int) $r->url_id, (int) $r->keyword_id);
        }

        return $created;
    }

    // -------------------------------------------------------------------------
    // Muster (relational — Population = Arena)
    // -------------------------------------------------------------------------

    /** Mehrere eigene URLs der Arena ranken für dasselbe Keyword. Eine je Keyword. */
    protected function cannibalization(SeoSignalDefinition $def, array $urlIds): int
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

        $created = 0;
        foreach ($rows as $keywordId => $group) {
            $urls = $group->unique('url_id');
            if ($urls->count() < $minUrls) {
                continue;
            }
            $first = $group->first();
            $vol = (int) $first->search_volume;
            $impact = $vol * ($urls->count() - 1);
            $competing = $urls->sortBy('position')->map(fn ($r) => ['url_id' => (int) $r->url_id, 'position' => (int) $r->position])->values()->all();

            $created += $this->persist($def, [
                'title' => "Kannibalisierung: \"{$first->keyword}\" — {$urls->count()} eigene URLs",
                'description' => "{$urls->count()} eigene Seiten der Arena ranken für \"{$first->keyword}\" ({$vol} Vol.). Konsolidieren oder entflechten.",
                'context' => ['pattern' => 'cannibalization', 'keyword' => $first->keyword, 'volume' => $vol, 'url_count' => $urls->count(), 'urls' => $competing, 'scope' => $def->scope_value, 'impact' => $impact],
            ], null, (int) $keywordId);
        }

        return $created;
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
