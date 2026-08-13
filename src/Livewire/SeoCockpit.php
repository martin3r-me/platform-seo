<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Models\SeoUrlSnapshot;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Agentur-Cockpit — das Kunden-Portfolio als Startseite der Agentur-Welt.
 *
 * Zeigt alle Kunden (über die Engagement-Ebene) als scannbare Karten mit ihren
 * aggregierten SEO-Kennzahlen. Klick → die Kunden-Perspektive. Plus Ablage-CTA
 * für noch nicht zugeordnete URLs. „Welche Kunden sind gesund, welche brauchen mich."
 */
class SeoCockpit extends Component
{
    use ResolvesTeamSettings;

    /** Dringlichkeits-Reihung der Signal-Severities für die „größter Hebel"-Aussage. */
    private const SEVERITY_RANK = ['critical' => 3, 'warning' => 2, 'watch' => 1, 'info' => 0];

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;
        $linker = app(SeoOrganizationLinker::class);

        // Root-only: Unterseiten nicht mitzählen.
        $childUrlIds = SeoUrlRelationship::where('team_id', $teamId)
            ->where('type', 'parent_child')
            ->pluck('target_url_id')->all();

        $customerIds = $linker->customerEntityIdsForTeam($teamId);

        // Roll-up: Portfolio-Karten sind nur die Top-Level-Kunden (Engagement-Ebene).
        // Kunden, die unter einem anderen Kunden hängen — z. B. die Marken einer Holding
        // (Foodpol → TM/Foodist/DOEC) — rollen in die Eltern-Karte, die den Subtree ohnehin
        // aggregiert. Die Marken bleiben per Drill-down (Klick → Perspektive) erreichbar.
        $descOf = [];
        foreach ($customerIds as $cid) {
            $descOf[(int) $cid] = $linker->descendantEntityIds((int) $cid);
        }
        $topLevelIds = [];
        $brandCounts = [];
        foreach ($customerIds as $cid) {
            $cid = (int) $cid;
            $isUnderAnother = false;
            $brands = 0;
            foreach ($customerIds as $other) {
                $other = (int) $other;
                if ($other === $cid) {
                    continue;
                }
                if (in_array($cid, $descOf[$other] ?? [], true)) {
                    $isUnderAnother = true;
                }
                if (in_array($other, $descOf[$cid] ?? [], true)) {
                    $brands++;
                }
            }
            if (! $isUnderAnother) {
                $topLevelIds[] = $cid;
                $brandCounts[$cid] = $brands;
            }
        }

        $names = [];
        $class = \Platform\Organization\Models\OrganizationEntity::class;
        if (class_exists($class) && ! empty($topLevelIds)) {
            try {
                foreach ($class::whereIn('id', $topLevelIds)->get(['id', 'name']) as $e) {
                    $names[(int) $e->id] = $e->name;
                }
            } catch (\Throwable $e) {
                // Organization nicht geladen
            }
        }

        $cards = [];
        $totalOpenRecs = 0;
        $cut = now()->subDays(30)->toDateString();

        foreach ($topLevelIds as $cid) {
            $subtree = $descOf[(int) $cid] ?? $linker->descendantEntityIds((int) $cid);
            $urlIds = $linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $subtree);

            $urls = collect();
            if (! empty($urlIds)) {
                $urls = SeoUrl::where('team_id', $teamId)
                    ->whereIn('id', $urlIds)
                    ->where('status', 'active')
                    ->where('is_own', true)
                    ->when(! empty($childUrlIds), fn ($q) => $q->whereNotIn('id', $childUrlIds))
                    ->get();
            }

            $custUrlIds = $urls->pluck('id')->all();
            $openRecs = 0;
            $visDelta = null;
            $topRec = null;
            if (! empty($custUrlIds)) {
                $recs = SeoSignal::whereIn('url_id', $custUrlIds)
                    ->where('signal_type', 'like', 'rec\_%')
                    ->whereIn('status', ['new', 'acknowledged'])
                    ->get(['id', 'title', 'severity', 'metric_delta']);
                $openRecs = $recs->count();
                $topRec = $recs->sortByDesc(function ($r) {
                    $rank = self::SEVERITY_RANK[$r->severity] ?? 0;
                    return $rank * 1_000_000 + abs((float) $r->metric_delta);
                })->first();

                $pastByUrl = [];
                foreach (SeoUrlSnapshot::whereIn('url_id', $custUrlIds)
                            ->whereDate('snapshot_date', '<=', $cut)
                            ->orderBy('snapshot_date')
                            ->get(['url_id', 'visibility_score']) as $s) {
                    $pastByUrl[$s->url_id] = (float) $s->visibility_score;
                }
                $past = array_sum($pastByUrl);
                if ($past > 0) {
                    $visDelta = (int) round((float) $urls->sum('visibility_score') - $past);
                }
            }
            $totalOpenRecs += $openRecs;

            $cards[] = [
                'id' => (int) $cid,
                'name' => $names[(int) $cid] ?? ('Kunde #'.$cid),
                'urls' => $urls->count(),
                'visibility' => round((float) $urls->sum('visibility_score'), 0),
                'keywords' => (int) $urls->sum('keyword_count'),
                'search_volume' => (int) $urls->sum('total_search_volume'),
                'open_recs' => $openRecs,
                'vis_delta' => $visDelta,
                'brands' => $brandCounts[(int) $cid] ?? 0,
                'insight' => $this->customerInsight($urls->count(), $openRecs, $topRec, $visDelta),
            ];
        }

        // Getrackte zuerst (nach Sichtbarkeit), untracked zuletzt.
        usort($cards, fn ($a, $b) => ($b['visibility'] <=> $a['visibility']) ?: ($b['urls'] <=> $a['urls']));

        return view('seo::livewire.seo-cockpit', [
            'cards' => $cards,
            'lists' => $this->listsForDashboard($teamId),
            'ablageCount' => $this->ablageCount($teamId, $linker, $childUrlIds),
            'totals' => [
                'customers' => count($cards),
                'tracked' => count(array_filter($cards, fn ($c) => $c['urls'] > 0)),
                'visibility' => array_sum(array_column($cards, 'visibility')),
                'recs' => $totalOpenRecs,
            ],
        ])->layout('platform::layouts.app');
    }

    /**
     * Die eine Aussage pro Kunden-Kachel: was ist los, was ist der nächste Hebel.
     * Priorität: offener Hebel (Empfehlung) → Abwärtsrisiko → Aufwärtstrend → stabil.
     *
     * @return array{text: string, tone: string}|null
     */
    protected function customerInsight(int $urlCount, int $openRecs, ?SeoSignal $topRec, ?int $visDelta): ?array
    {
        if ($urlCount === 0) {
            return null; // untracked — die Kachel zeigt dafür den „URLs aufhängen"-Hinweis
        }

        if ($topRec && $openRecs > 0) {
            $tone = match ($topRec->severity) {
                'critical' => 'danger',
                'warning' => 'warning',
                default => 'info',
            };
            $prefix = $openRecs > 1 ? 'Größter Hebel: ' : 'Hebel: ';

            return ['text' => $prefix.$topRec->title, 'tone' => $tone];
        }

        if ($visDelta !== null && $visDelta <= -5) {
            return ['text' => 'Sichtbarkeit rutscht ('.$visDelta.') — dranbleiben', 'tone' => 'danger'];
        }

        if ($visDelta !== null && $visDelta >= 5) {
            return ['text' => 'Im Aufwind (+'.$visDelta.')', 'tone' => 'success'];
        }

        return ['text' => 'Stabil — kein akuter Handlungsbedarf', 'tone' => 'muted'];
    }

    /**
     * Die Listen (Markt-/Themen-Achse, quer zum Org-Baum) mit Überschneidungs-Zähler.
     * Overlap = Keywords, die auf ≥2 eigenen URLs derselben Liste ranken (Kannibalisierung
     * innerhalb der Liste). Eine aggregierte Query über alle Listen.
     *
     * @return array<int, array{id:int, name:string, urls:int, overlaps:int}>
     */
    protected function listsForDashboard(int $teamId): array
    {
        $lists = SeoUrlList::whereHas('urls', fn ($q) => $q->where('seo_urls.team_id', $teamId))
            ->withCount('urls')
            ->orderBy('name')
            ->get();

        if ($lists->isEmpty()) {
            return [];
        }

        $overlaps = [];
        try {
            $rows = DB::table('seo_url_list_entries as e')
                ->join('seo_url_keywords as uk', 'uk.url_id', '=', 'e.url_id')
                ->join('seo_urls as u', 'u.id', '=', 'e.url_id')
                ->where('u.team_id', $teamId)
                ->where('u.is_own', true)
                ->whereNotNull('uk.position')
                ->groupBy('e.list_id', 'uk.keyword_id')
                ->havingRaw('COUNT(DISTINCT uk.url_id) >= 2')
                ->get(['e.list_id']);
            foreach ($rows as $r) {
                $overlaps[(int) $r->list_id] = ($overlaps[(int) $r->list_id] ?? 0) + 1;
            }
        } catch (\Throwable $e) {
            // Tabelle/Spalten fehlen — Overlap bleibt 0
        }

        return $lists->map(fn ($list) => [
            'id' => (int) $list->id,
            'name' => $list->name,
            'urls' => (int) $list->urls_count,
            'overlaps' => $overlaps[(int) $list->id] ?? 0,
        ])->all();
    }

    /** Anzahl Agentur-URLs ohne Kontext (root-only, nicht modul-eigen). */
    protected function ablageCount(int $teamId, SeoOrganizationLinker $linker, array $childUrlIds): int
    {
        $own = SeoUrl::where('team_id', $teamId)
            ->where('status', 'active')
            ->where('is_own', true)
            ->when(! empty($childUrlIds), fn ($q) => $q->whereNotIn('id', $childUrlIds))
            ->pluck('id')->map(fn ($i) => (int) $i)->all();

        if (empty($own)) {
            return 0;
        }

        $moduleOwned = SeoUrlRegistration::whereIn('url_id', $own)
            ->where('source_module', '!=', 'seo')
            ->pluck('url_id')->map(fn ($i) => (int) $i)->unique()->all();
        $linked = $linker->linkedLinkableIds(SeoOrganizationLinker::ALIAS_URL, $own);
        $exclude = array_flip(array_merge($moduleOwned, $linked));

        return count(array_filter($own, fn ($id) => ! isset($exclude[$id])));
    }
}
