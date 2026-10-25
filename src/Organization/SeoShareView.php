<?php

namespace Platform\Seo\Organization;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Contracts\ProvidesShareView;
use Platform\Seo\Models\SeoContentBrief;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoScopeMetrics;

/**
 * Kunden-Tor · „Sichtbarkeit": wie die Websites des Kunden gefunden werden —
 * Besucher, Google-Klicks, Rankings (Gewinner/Verlierer), KI-Erwähnungen, Themen,
 * Seiten und Inhalte. Ein Bereich (nav=section) für alle verknüpften eigenen URLs
 * samt Unterseiten. Bewusst ohne Kosten/Budgets, Wettbewerber, Rohdaten und
 * interne Notizen; nur lokal gesammelte Daten (keine Live-Abrufe).
 */
class SeoShareView implements ProvidesShareView
{
    public function shareTypes(): array
    {
        return [
            'seo_url' => ['label' => 'Sichtbarkeit', 'singular' => 'Website', 'icon' => 'magnifying-glass', 'slug' => 'sichtbarkeit',
                'group' => 'wirkung', 'nav' => 'section', 'order' => 20],
        ];
    }

    public function shareItems(string $alias, Collection $models, array $options): array
    {
        return $this->ownUrls($models)->map(fn (SeoUrl $u) => [
            'id' => $u->id,
            'title' => $u->domain . (trim((string) $u->path, '/') !== '' ? $u->path : ''),
            'state' => 'active',
            'state_label' => 'Beobachtet',
            'updated_at' => $u->updated_at,
        ])->values()->all();
    }

    public function shareSummary(string $alias, Collection $models, array $options): ?array
    {
        $d = $this->data($models);
        if (! $d) {
            return null;
        }
        $net = $d['movers']['improved'] - $d['movers']['declined'];

        return [
            'title' => 'Sichtbarkeit · ' . implode(', ', array_slice($d['domains'], 0, 2)),
            'status' => $d['positions']['top10'] > 0
                ? ['text' => ($net > 0 ? '↑ ' : ($net < 0 ? '↓ ' : '')) . $d['positions']['top10'] . ' in Top 10', 'tone' => $net >= 0 ? 'success' : 'warning']
                : null,
            'kpis' => array_values(array_filter([
                $d['traffic']['visitors'] !== null ? ['label' => 'Besucher (30 T.)', 'value' => $this->num($d['traffic']['visitors']), 'hint' => $d['traffic']['organic'] ? $this->num($d['traffic']['organic']) . ' über Suche' : null] : null,
                $d['gsc']['clicks'] !== null ? ['label' => 'Klicks aus Google (28 T.)', 'value' => $this->num($d['gsc']['clicks']), 'hint' => $this->deltaHint($d['gsc']['clicks_delta']), 'tone' => $this->deltaTone($d['gsc']['clicks_delta'])] : null,
                ['label' => 'Keywords in Top 10', 'value' => $this->num($d['positions']['top10']), 'hint' => $d['positions']['top3'] . ' davon in Top 3'],
                ['label' => 'Bewegung', 'value' => '↑ ' . $d['movers']['improved'] . '  ↓ ' . $d['movers']['declined'], 'hint' => 'seit der letzten Messung'],
                $d['gsc']['impressions'] !== null ? ['label' => 'Einblendungen (28 T.)', 'value' => $this->num($d['gsc']['impressions']), 'hint' => $d['gsc']['position'] ? 'Ø Position ' . number_format($d['gsc']['position'], 1, ',', '.') : null] : null,
                $d['llm']['mentions'] ? ['label' => 'Erwähnungen in KI-Antworten', 'value' => $this->num($d['llm']['mentions']), 'hint' => 'ChatGPT, Google KI u. a.'] : null,
            ])),
        ];
    }

    public function shareSection(string $alias, Collection $models, array $options): ?array
    {
        $d = $this->data($models);
        if (! $d) {
            return null;
        }
        $sub = $options['sub'] ?? null;
        if ($sub !== null) {
            return null; // (noch) keine Unterseiten
        }

        return [
            'title' => 'Sichtbarkeit',
            'subtitle' => implode(' · ', $d['domains']),
            'description' => 'Wie Ihre Website in Google und in KI-Antworten gefunden wird — gemessen aus Search Console, Besucherzahlen und Ranking-Daten. Stand ' . ($d['measured_at'] ? $d['measured_at']->copy()->locale('de')->isoFormat('D. MMMM YYYY') : 'unbekannt') . '.',
            'state' => 'active',
            'state_label' => 'Live gemessen',
            'progress' => null,
            'facts' => array_values(array_filter([
                ['label' => 'Keywords in Top 10', 'value' => $this->num($d['positions']['top10']) . ' von ' . $this->num($d['positions']['ranking'])],
                $d['traffic']['visitors'] !== null ? ['label' => 'Besucher (30 T.)', 'value' => $this->num($d['traffic']['visitors'])] : null,
                $d['gsc']['clicks'] !== null ? ['label' => 'Klicks aus Google (28 T.)', 'value' => $this->num($d['gsc']['clicks']) . ($this->deltaHint($d['gsc']['clicks_delta']) ? ' (' . $this->deltaHint($d['gsc']['clicks_delta']) . ')' : '')] : null,
                ['label' => 'Seiten', 'value' => (string) count($d['pages'])],
            ])),
            'tabs' => array_values(array_filter([
                ['key' => 'ueberblick', 'label' => 'Überblick', 'count' => null, 'view' => 'seo::share.overview', 'data' => ['d' => $d]],
                ['key' => 'keywords', 'label' => 'Keywords', 'count' => count($d['keywords']), 'view' => 'seo::share.keywords', 'data' => ['d' => $d]],
                $d['clusters'] ? ['key' => 'themen', 'label' => 'Themen', 'count' => count($d['clusters']), 'view' => 'seo::share.topics', 'data' => ['d' => $d]] : null,
                ['key' => 'seiten', 'label' => 'Seiten', 'count' => count($d['pages']), 'view' => 'seo::share.pages', 'data' => ['d' => $d]],
                $d['briefs'] ? ['key' => 'inhalte', 'label' => 'Inhalte', 'count' => count($d['briefs']), 'view' => 'seo::share.contents', 'data' => ['d' => $d]] : null,
            ])),
        ];
    }

    // ── Daten ──────────────────────────────────────────────────

    /** Eigene, aktive URLs der Freigabe. */
    protected function ownUrls(Collection $models): Collection
    {
        return $models->filter(fn ($u) => $u instanceof SeoUrl && $u->is_own && $u->status === 'active');
    }

    /** Alles für Kurzstatus und Seite — 10 Minuten gecacht je URL-Menge. */
    protected function data(Collection $models): ?array
    {
        $roots = $this->ownUrls($models);
        if ($roots->isEmpty()) {
            return null;
        }
        $teamId = (int) $roots->first()->team_id;
        $rootIds = $roots->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all();

        return Cache::remember('seo-share:' . $teamId . ':' . md5(implode(',', $rootIds)), now()->addMinutes(10), fn () => $this->build($teamId, $rootIds));
    }

    protected function build(int $teamId, array $rootIds): array
    {
        // Unterseiten (parent_child) gehören zur Website dazu
        $ids = $rootIds;
        for ($i = 0, $frontier = $rootIds; $frontier && $i < 6; $i++) {
            $frontier = SeoUrlRelationship::where('type', 'parent_child')->whereIn('source_url_id', $frontier)
                ->pluck('target_url_id')->map(fn ($x) => (int) $x)->diff($ids)->values()->all();
            $ids = array_merge($ids, $frontier);
        }
        $urls = SeoUrl::whereIn('id', $ids)->where('team_id', $teamId)->where('is_own', true)->get();
        $ids = $urls->pluck('id')->all();

        // Keywords: beste Position je Keyword über alle Seiten
        $kwRows = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $ids)
            ->whereNull('k.retired_at')
            ->whereNotNull('uk.position')
            ->select('k.id', 'k.keyword', 'k.search_volume', 'k.cluster_id', 'uk.url_id', 'uk.position', 'uk.previous_position')
            ->get()
            ->groupBy('id')
            ->map(fn ($g) => $g->sortBy('position')->first());

        $urlPath = $urls->mapWithKeys(fn ($u) => [$u->id => ($u->path ?: '/')]);
        $keywords = $kwRows->map(fn ($r) => [
            'keyword' => $r->keyword,
            'position' => (int) $r->position,
            'previous' => $r->previous_position !== null ? (int) $r->previous_position : null,
            'change' => $r->previous_position !== null ? (int) $r->previous_position - (int) $r->position : null,
            'volume' => (int) $r->search_volume,
            'page' => $urlPath[$r->url_id] ?? null,
        ])->sortBy([['position', 'asc'], ['volume', 'desc']])->values();

        $moved = $keywords->filter(fn ($k) => $k['change'] !== null && $k['change'] !== 0);
        $gscNow = $urls->whereNotNull('gsc_fetched_at');
        $clicks = $gscNow->isNotEmpty() ? (int) $gscNow->sum('gsc_clicks_28d') : null;
        $clicksBefore = $this->gscClicksAround($ids, now()->subDays(30));

        $trafficUrls = $urls->whereNotNull('traffic_fetched_at');
        $briefs = $this->briefs($teamId, $urls);

        return [
            'domains' => $urls->pluck('domain')->filter()->unique()->values()->all(),
            'measured_at' => collect([$urls->max('gsc_fetched_at'), $urls->max('traffic_fetched_at'), $urls->max('updated_at')])->filter()->max(),
            'headline' => app(SeoScopeMetrics::class)->headline($ids),
            'positions' => [
                'ranking' => $keywords->count(),
                'top3' => $keywords->where('position', '<=', 3)->count(),
                'top10' => $keywords->where('position', '<=', 10)->count(),
                'top20' => $keywords->where('position', '<=', 20)->count(),
                'dist' => [
                    '1–3' => $keywords->whereBetween('position', [1, 3])->count(),
                    '4–10' => $keywords->whereBetween('position', [4, 10])->count(),
                    '11–20' => $keywords->whereBetween('position', [11, 20])->count(),
                    '21–50' => $keywords->whereBetween('position', [21, 50])->count(),
                    '51+' => $keywords->where('position', '>', 50)->count(),
                ],
            ],
            'movers' => [
                'improved' => $moved->where('change', '>', 0)->count(),
                'declined' => $moved->where('change', '<', 0)->count(),
                'up' => $moved->where('change', '>', 0)->sortByDesc(fn ($k) => [$k['change'] * max(1, $k['volume'])])->take(8)->values()->all(),
                'down' => $moved->where('change', '<', 0)->sortBy(fn ($k) => [$k['change'] * max(1, $k['volume'])])->take(8)->values()->all(),
            ],
            'gsc' => [
                'clicks' => $clicks,
                'impressions' => $gscNow->isNotEmpty() ? (int) $gscNow->sum('gsc_impressions_28d') : null,
                'position' => $gscNow->isNotEmpty() && $gscNow->sum('gsc_impressions_28d') > 0
                    ? (float) $gscNow->sum(fn ($u) => (float) $u->gsc_avg_position * (int) $u->gsc_impressions_28d) / max(1, $gscNow->sum('gsc_impressions_28d'))
                    : null,
                'clicks_delta' => $clicks !== null && $clicksBefore ? (int) round(($clicks - $clicksBefore) / $clicksBefore * 100) : null,
                'queries' => $this->topQueries($gscNow),
            ],
            'traffic' => [
                'visitors' => $trafficUrls->isNotEmpty() ? (int) $trafficUrls->sum('visitors_30d') : null,
                'organic' => $trafficUrls->isNotEmpty() ? (int) $trafficUrls->sum('organic_visitors_30d') : null,
                'weeks' => $this->visitorWeeks($ids),
            ],
            'llm' => [
                'mentions' => (int) $urls->sum('llm_mentions'),
                'ai_volume' => (int) $urls->sum('llm_ai_search_volume'),
            ],
            'clusters' => app(SeoScopeMetrics::class)->penetration($ids)['clusters']
                ->map(fn ($c) => ['name' => $c['name'], 'ist' => $c['ist'], 'soll' => $c['soll'], 'pct' => $c['pct'], 'volume' => $c['volume']])
                ->take(30)->values()->all(),
            'keywords' => $keywords->take(200)->all(),
            'pages' => $urls->sortByDesc(fn ($u) => [(int) $u->visitors_30d, (int) $u->gsc_clicks_28d])->map(fn ($u) => [
                'url' => $u->url,
                'path' => $u->path ?: '/',
                'domain' => $u->domain,
                'visitors' => $u->traffic_fetched_at ? (int) $u->visitors_30d : null,
                'clicks' => $u->gsc_fetched_at ? (int) $u->gsc_clicks_28d : null,
                'keywords' => (int) $u->keyword_count,
                'position' => $u->gsc_avg_position ? (float) $u->gsc_avg_position : null,
            ])->values()->all(),
            'briefs' => $briefs,
        ];
    }

    /** GSC-Klicks (28-Tage-Fenster) aller Seiten zum Stichtag — für die Veränderung. */
    protected function gscClicksAround(array $ids, Carbon $day): ?int
    {
        try {
            $rows = DB::table('seo_gsc_snapshots')->whereIn('url_id', $ids)
                ->whereBetween('snapshot_date', [$day->copy()->subDays(4)->toDateString(), $day->copy()->addDays(3)->toDateString()])
                ->orderBy('snapshot_date')->get(['url_id', 'clicks_28d']);
        } catch (\Throwable) {
            return null;
        }
        $sum = (int) $rows->groupBy('url_id')->sum(fn ($g) => (int) $g->last()->clicks_28d);

        return $sum > 0 ? $sum : null;
    }

    /** Besucher je Woche (12 Wochen) aus den täglichen Plausible-Werten. */
    protected function visitorWeeks(array $ids): array
    {
        $from = now()->startOfWeek()->subWeeks(11);
        try {
            $rows = DB::table('seo_url_traffic')->whereIn('url_id', $ids)->where('date', '>=', $from->toDateString())
                ->get(['date', 'visitors']);
        } catch (\Throwable) {
            return [];
        }
        if ($rows->isEmpty()) {
            return [];
        }
        $weeks = [];
        for ($i = 0; $i < 12; $i++) {
            $w = $from->copy()->addWeeks($i);
            $weeks[$w->format('Y-m-d')] = ['label' => 'KW ' . $w->isoWeek(), 'from' => $w->copy()->locale('de')->isoFormat('D. MMM'), 'visitors' => 0];
        }
        foreach ($rows as $r) {
            $k = Carbon::parse($r->date)->startOfWeek()->format('Y-m-d');
            if (isset($weeks[$k])) {
                $weeks[$k]['visitors'] += (int) $r->visitors;
            }
        }

        return array_values($weeks);
    }

    /** Häufigste Suchanfragen aus der Search Console (über alle Seiten). */
    protected function topQueries(Collection $urls): array
    {
        $out = [];
        foreach ($urls as $u) {
            foreach ((array) $u->gsc_top_queries as $q) {
                $term = is_array($q) ? ($q['query'] ?? $q['keyword'] ?? null) : null;
                if (! $term) {
                    continue;
                }
                $out[$term] ??= ['query' => $term, 'clicks' => 0, 'impressions' => 0];
                $out[$term]['clicks'] += (int) ($q['clicks'] ?? 0);
                $out[$term]['impressions'] += (int) ($q['impressions'] ?? 0);
            }
        }

        return collect($out)->sortByDesc(fn ($q) => [$q['clicks'], $q['impressions']])->take(15)->values()->all();
    }

    /** Inhalte für die Website (Content-Briefs dieses Teams) — nur Titel, Art, Stand. */
    protected function briefs(int $teamId, Collection $urls): array
    {
        $domains = $urls->pluck('domain')->filter()->unique();
        if ($domains->isEmpty()) {
            return [];
        }
        $labels = ['draft' => 'In Planung', 'briefed' => 'Konzipiert', 'queued' => 'Eingeplant', 'in_production' => 'In Arbeit', 'published' => 'Veröffentlicht'];

        return SeoContentBrief::query()
            ->where('team_id', $teamId)
            ->where(function ($q) use ($domains) {
                foreach ($domains as $d) {
                    $q->orWhere('target_url', 'like', '%' . $d . '%')->orWhere('published_url', 'like', '%' . $d . '%');
                }
            })
            ->orderByRaw("CASE WHEN status = 'published' THEN 1 ELSE 0 END")
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get()
            ->map(fn ($b) => [
                'name' => $b->name,
                'type' => $b->content_type,
                'status' => $b->status,
                'status_label' => $labels[$b->status] ?? $b->status,
                'url' => $b->published_url ?: null,
                'published_at' => $b->published_at,
                'updated_at' => $b->updated_at,
            ])->all();
    }

    protected function num(int|float|null $n): string
    {
        return number_format((float) $n, 0, ',', '.');
    }

    protected function deltaHint(?int $pct): ?string
    {
        return $pct === null ? null : ($pct > 0 ? '+' : '') . $pct . ' % ggü. Vormonat';
    }

    protected function deltaTone(?int $pct): ?string
    {
        return $pct === null ? null : ($pct >= 0 ? 'success' : 'danger');
    }
}
