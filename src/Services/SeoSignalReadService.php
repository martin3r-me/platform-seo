<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\SeoSignalServiceInterface;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRegistration;

/**
 * Zentrale Signal-Lese-Schicht (Keystone).
 *
 * Liefert das vollständige Per-URL-Signal-Bündel und projiziert es zwei Wege:
 *  - getSignalsForNode()  → nach Organisations-Knoten (Agentur/Roll-up in den Baum)
 *  - getSignalsBySource() → nach Fremdmodul-Quelle (Modul-Read-Back, z.B. Syltjunkie)
 *
 * Reine Lese-Schicht über die vorhandenen Collector-Daten — keine eigene Datenhaltung.
 * Die Bündel-Form ist die stabile Vertragsfläche, auf der beide Muster andocken.
 */
class SeoSignalReadService implements SeoSignalServiceInterface
{
    public function __construct(
        protected SeoOrganizationLinker $linker,
    ) {}

    /**
     * Signal-Bündel für eine einzelne URL.
     */
    public function getSignals(int $teamId, string $url): ?array
    {
        $seoUrl = SeoUrl::where('team_id', $teamId)
            ->where('url_hash', SeoUrl::hashUrl($url))
            ->first();

        if (! $seoUrl) {
            return null;
        }

        return $this->buildBundles(collect([$seoUrl]))[$seoUrl->id] ?? null;
    }

    /**
     * Signal-Bündel aller eigenen/verlinkten URLs eines Organisations-Knotens.
     *
     * @return array<int,array>  [url_id => Bündel]
     */
    public function getSignalsForNode(int $teamId, int $entityId): array
    {
        $urlIds = $this->linker->linkableIdsForNode(SeoOrganizationLinker::ALIAS_URL, $entityId);
        if (empty($urlIds)) {
            return [];
        }

        $urls = SeoUrl::where('team_id', $teamId)->whereIn('id', $urlIds)->get();

        return $this->buildBundles($urls);
    }

    /**
     * Signal-Bündel für die von einem Fremdmodul registrierten URLs — verschlüsselt
     * nach dessen source_id, damit der Konsument nie seo_url-IDs kennen muss.
     *
     * @param  int[]  $sourceIds
     * @return array<int,array>  [source_id => Bündel]
     */
    public function getSignalsBySource(int $teamId, string $sourceModule, array $sourceIds): array
    {
        if (empty($sourceIds)) {
            return [];
        }

        $registrations = SeoUrlRegistration::where('source_module', $sourceModule)
            ->whereIn('source_id', $sourceIds)
            ->get(['url_id', 'source_id']);

        if ($registrations->isEmpty()) {
            return [];
        }

        // url_id → [source_id, ...] (eine URL kann mehrfach registriert sein)
        $sourceByUrl = [];
        foreach ($registrations as $reg) {
            $sourceByUrl[$reg->url_id][] = $reg->source_id;
        }

        $urls = SeoUrl::where('team_id', $teamId)
            ->whereIn('id', array_keys($sourceByUrl))
            ->get();

        $bundles = $this->buildBundles($urls);

        $result = [];
        foreach ($bundles as $urlId => $bundle) {
            foreach ($sourceByUrl[$urlId] ?? [] as $sourceId) {
                $result[$sourceId] = $bundle;
            }
        }

        return $result;
    }

    /**
     * Ein Signal (z.B. eine Empfehlung) zentral als erledigt markieren.
     * Team-scoped; nur offene Signale werden geschlossen (idempotent).
     */
    public function resolveSignal(int $teamId, int $signalId): bool
    {
        $updated = \Platform\Seo\Models\SeoSignal::where('id', $signalId)
            ->where('team_id', $teamId)
            ->where('status', '!=', 'resolved')
            ->update(['status' => 'resolved']);

        return $updated > 0;
    }

    /**
     * Baut die Signal-Bündel für eine Menge URLs — bulk, ohne N+1.
     *
     * @return array<int,array>  [url_id => Bündel]
     */
    protected function buildBundles(Collection $urls): array
    {
        if ($urls->isEmpty()) {
            return [];
        }

        $urls->load(['keywords' => fn ($q) => $q->with('cluster'), 'onPage']);
        $urlIds = $urls->pluck('id')->all();
        $since = now()->subDays(30)->toDateString();

        // GSC — Seiten-Aggregat (keyword_id null) der letzten 30 Tage
        $gsc = DB::table('seo_url_gsc_data')
            ->whereIn('url_id', $urlIds)
            ->whereNull('keyword_id')
            ->where('date', '>=', $since)
            ->select('url_id',
                DB::raw('SUM(impressions) as impressions'),
                DB::raw('SUM(clicks) as clicks'),
                DB::raw('AVG(avg_position) as avg_position'))
            ->groupBy('url_id')
            ->get()
            ->keyBy('url_id');

        // Backlinks — Top-Domains je URL (nach Authority), begrenzt gruppiert in PHP
        $backlinkRows = DB::table('seo_url_backlinks')
            ->whereIn('url_id', $urlIds)
            ->where('is_active', true)
            ->orderByDesc('source_domain_authority')
            ->limit(1000)
            ->get(['url_id', 'source_domain', 'source_domain_authority']);
        $topDomains = [];
        foreach ($backlinkRows as $row) {
            if (count($topDomains[$row->url_id] ?? []) >= 5) {
                continue;
            }
            $topDomains[$row->url_id][] = [
                'domain' => $row->source_domain,
                'authority' => $row->source_domain_authority !== null ? (int) $row->source_domain_authority : null,
            ];
        }

        // Offene Empfehlungen je URL
        $recRows = DB::table('seo_signals')
            ->whereIn('url_id', $urlIds)
            ->where('signal_type', 'like', 'rec\_%')
            ->where('status', '!=', 'resolved')
            ->orderByDesc('detected_at')
            ->get(['id', 'url_id', 'signal_type', 'severity', 'title', 'status', 'detected_at']);
        $recsByUrl = [];
        foreach ($recRows as $row) {
            $recsByUrl[$row->url_id][] = [
                'id' => (int) $row->id,
                'type' => $row->signal_type,
                'severity' => $row->severity,
                'title' => $row->title,
                'status' => $row->status,
                'detected_at' => $row->detected_at ? substr((string) $row->detected_at, 0, 10) : null,
            ];
        }

        $result = [];
        foreach ($urls as $url) {
            $result[$url->id] = $this->bundle($url, $gsc->get($url->id), $topDomains[$url->id] ?? [], $recsByUrl[$url->id] ?? []);
        }

        return $result;
    }

    protected function bundle(SeoUrl $url, ?object $gsc, array $topDomains, array $recommendations): array
    {
        $keywords = $url->keywords;

        $rankings = $keywords
            ->filter(fn ($kw) => $kw->pivot->position !== null)
            ->sortBy('pivot.position')
            ->take(20)
            ->map(fn ($kw) => [
                'keyword' => $kw->keyword,
                'position' => (int) $kw->pivot->position,
                'previous_position' => $kw->pivot->previous_position !== null ? (int) $kw->pivot->previous_position : null,
                'search_engine' => $kw->pivot->search_engine,
                'device' => $kw->pivot->device,
                'updated_at' => $kw->pivot->position_updated_at ? substr((string) $kw->pivot->position_updated_at, 0, 10) : null,
            ])->values()->all();

        $demand = $keywords
            ->sortByDesc('search_volume')
            ->take(20)
            ->map(fn ($kw) => [
                'keyword' => $kw->keyword,
                'search_volume' => $kw->search_volume,
                'keyword_difficulty' => $kw->keyword_difficulty,
                'search_intent' => $kw->search_intent,
                'cluster' => $kw->cluster?->name,
            ])->values()->all();

        return [
            'url' => $url->url,
            'url_id' => $url->id,
            'uuid' => $url->uuid,
            'domain' => $url->domain,
            'path' => $url->path,
            'is_own' => (bool) $url->is_own,
            'status' => $url->status,
            'priority' => $url->priority,
            'visibility' => (float) $url->visibility_score,
            'rankings' => $rankings,
            'keyword_demand' => $demand,
            'traffic' => [
                'visitors_30d' => (int) $url->visitors_30d,
                'pageviews_30d' => (int) $url->pageviews_30d,
                'fetched_at' => $url->traffic_fetched_at?->toIso8601String(),
            ],
            'gsc' => $gsc ? [
                'impressions' => (int) $gsc->impressions,
                'clicks' => (int) $gsc->clicks,
                'avg_position' => $gsc->avg_position !== null ? round((float) $gsc->avg_position, 1) : null,
            ] : null,
            'backlinks' => [
                'count' => (int) $url->backlink_count,
                'top_domains' => $topDomains,
            ],
            'on_page' => $url->onPage ? [
                'title' => $url->onPage->title,
                'h1' => $url->onPage->h1,
                'word_count' => $url->onPage->word_count,
                'overall_score' => $url->onPage->overall_score,
            ] : null,
            'recommendations' => $recommendations,
            'last_crawled_at' => $url->last_crawled_at?->toIso8601String(),
        ];
    }
}
