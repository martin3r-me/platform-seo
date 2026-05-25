<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\SeoUrlServiceInterface;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Models\SeoUrlRelationship;

class SeoUrlService implements SeoUrlServiceInterface
{
    public function __construct(
        protected SeoUrlPipelineService $pipeline,
    ) {}

    public function register(int $teamId, string $url, array $options = []): array
    {
        $normalized = SeoUrl::normalizeUrl($url);
        $hash = SeoUrl::hashUrl($url);

        $isOwn = $options['is_own'] ?? true;
        $priority = $options['priority'] ?? ($isOwn
            ? config('seo.priority.own_url_default', 70)
            : config('seo.priority.competitor_url_default', 30));

        // FirstOrCreate the URL
        $seoUrl = SeoUrl::withTrashed()->firstOrCreate(
            ['team_id' => $teamId, 'url_hash' => $hash],
            [
                'url' => $normalized,
                'domain' => parse_url($normalized, PHP_URL_HOST) ?? '',
                'path' => parse_url($normalized, PHP_URL_PATH) ?? '/',
                'is_own' => $isOwn,
                'priority' => $priority,
                'status' => 'active',
            ],
        );

        // Restore if soft-deleted
        if ($seoUrl->trashed()) {
            $seoUrl->restore();
            $seoUrl->update(['status' => 'active']);
        }

        // Update priority if higher
        if ($priority > $seoUrl->priority) {
            $seoUrl->update(['priority' => $priority]);
        }

        // Create registration
        $sourceModule = $options['source_module'] ?? 'seo';
        $sourceType = $options['source_type'] ?? null;
        $sourceId = $options['source_id'] ?? null;
        $reason = $options['reason'] ?? 'manual';

        $registration = SeoUrlRegistration::firstOrCreate(
            [
                'url_id' => $seoUrl->id,
                'source_module' => $sourceModule,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
            ],
            [
                'reason' => $reason,
                'meta' => $options['meta'] ?? null,
            ],
        );

        // Attach keywords if provided
        if (! empty($options['keywords'])) {
            $this->attachKeywordsToUrl($teamId, $seoUrl, $options['keywords']);
        }

        return [
            'url_id' => $seoUrl->id,
            'created' => $seoUrl->wasRecentlyCreated,
            'registration_id' => $registration->id,
        ];
    }

    public function unregister(int $teamId, string $url, string $sourceModule, ?string $sourceType = null, ?int $sourceId = null): array
    {
        $hash = SeoUrl::hashUrl($url);
        $seoUrl = SeoUrl::where('team_id', $teamId)->where('url_hash', $hash)->first();

        if (! $seoUrl) {
            return ['removed' => false, 'url_deleted' => false];
        }

        $deleted = SeoUrlRegistration::where('url_id', $seoUrl->id)
            ->where('source_module', $sourceModule)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->delete();

        if ($deleted === 0) {
            return ['removed' => false, 'url_deleted' => false];
        }

        // Check if any registrations remain
        $remainingRegistrations = SeoUrlRegistration::where('url_id', $seoUrl->id)->exists();

        if (! $remainingRegistrations) {
            $seoUrl->delete(); // Soft delete

            return ['removed' => true, 'url_deleted' => true];
        }

        return ['removed' => true, 'url_deleted' => false];
    }

    public function getData(int $teamId, string $url): ?array
    {
        $hash = SeoUrl::hashUrl($url);
        $seoUrl = SeoUrl::where('team_id', $teamId)
            ->where('url_hash', $hash)
            ->with(['keywords', 'onPage', 'registrations'])
            ->first();

        if (! $seoUrl) {
            return null;
        }

        return $this->formatUrlData($seoUrl);
    }

    public function getDataBatch(int $teamId, array $urls): array
    {
        $hashes = array_map(fn ($url) => SeoUrl::hashUrl($url), $urls);

        $seoUrls = SeoUrl::where('team_id', $teamId)
            ->whereIn('url_hash', $hashes)
            ->with(['keywords', 'onPage'])
            ->get();

        $result = [];
        foreach ($seoUrls as $seoUrl) {
            $result[$seoUrl->url] = $this->formatUrlData($seoUrl);
        }

        return $result;
    }

    public function getUrls(int $teamId, array $filters = []): Collection
    {
        $query = SeoUrl::where('team_id', $teamId);

        if (isset($filters['is_own'])) {
            $query->where('is_own', $filters['is_own']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['domain'])) {
            $query->where('domain', $filters['domain']);
        }
        if (isset($filters['min_priority'])) {
            $query->where('priority', '>=', $filters['min_priority']);
        }
        if (isset($filters['search'])) {
            $query->where('url', 'like', '%'.$filters['search'].'%');
        }

        $orderBy = $filters['order_by'] ?? 'priority';
        $orderDir = $filters['order_dir'] ?? 'desc';
        $query->orderBy($orderBy, $orderDir);

        $limit = $filters['limit'] ?? 100;

        return $query->limit($limit)->get();
    }

    public function getRelationships(int $teamId, string $url, array $types = []): array
    {
        $hash = SeoUrl::hashUrl($url);
        $seoUrl = SeoUrl::where('team_id', $teamId)->where('url_hash', $hash)->first();

        if (! $seoUrl) {
            return [];
        }

        $query = SeoUrlRelationship::where(function ($q) use ($seoUrl) {
            $q->where('source_url_id', $seoUrl->id)
                ->orWhere('target_url_id', $seoUrl->id);
        });

        if (! empty($types)) {
            $query->whereIn('type', $types);
        }

        return $query->with(['sourceUrl', 'targetUrl'])->get()->map(function ($rel) use ($seoUrl) {
            $isSource = $rel->source_url_id === $seoUrl->id;

            return [
                'type' => $rel->type,
                'direction' => $isSource ? 'outgoing' : 'incoming',
                'related_url' => $isSource ? $rel->targetUrl->url : $rel->sourceUrl->url,
                'strength' => $rel->strength,
                'detected_at' => $rel->detected_at?->toIso8601String(),
            ];
        })->toArray();
    }

    public function getKeywordsForUrl(int $teamId, string $url): Collection
    {
        $hash = SeoUrl::hashUrl($url);
        $seoUrl = SeoUrl::where('team_id', $teamId)->where('url_hash', $hash)->first();

        if (! $seoUrl) {
            return collect();
        }

        return $seoUrl->keywords;
    }

    public function getUrlsForKeyword(int $teamId, string $keyword): Collection
    {
        $seoKeyword = SeoKeyword::where('team_id', $teamId)
            ->where('keyword', strtolower(trim($keyword)))
            ->first();

        if (! $seoKeyword) {
            return collect();
        }

        return SeoUrl::where('team_id', $teamId)
            ->whereHas('keywords', fn ($q) => $q->where('seo_keywords.id', $seoKeyword->id))
            ->get();
    }

    public function enrich(int $teamId, ?string $url = null, array $collectors = [], bool $force = false, ?array $urlIds = null): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->first();
        if (! $settings) {
            return ['urls_processed' => 0, 'collectors_run' => [], 'cost_cents' => 0];
        }

        // Single URL by string
        if ($url) {
            $hash = SeoUrl::hashUrl($url);
            $seoUrl = SeoUrl::where('team_id', $teamId)->where('url_hash', $hash)->first();
            if (! $seoUrl) {
                return ['urls_processed' => 0, 'collectors_run' => [], 'cost_cents' => 0];
            }

            $result = $this->pipeline->runForUrl($settings, $seoUrl, $collectors, $force);

            return [
                'urls_processed' => $result['urls_processed'],
                'collectors_run' => $result['collectors_run'],
                'cost_cents' => $result['total_cost_cents'],
            ];
        }

        // Multiple URLs by IDs
        if ($urlIds) {
            $result = $this->pipeline->runPipeline($settings, [
                'url_ids' => $urlIds,
                'collectors' => $collectors,
                'force' => $force,
            ]);

            return [
                'urls_processed' => $result['urls_processed'],
                'collectors_run' => $result['collectors_run'],
                'cost_cents' => $result['total_cost_cents'],
            ];
        }

        $result = $this->pipeline->runPipeline($settings, [
            'collectors' => $collectors,
            'force' => $force,
        ]);

        return [
            'urls_processed' => $result['urls_processed'],
            'collectors_run' => $result['collectors_run'],
            'cost_cents' => $result['total_cost_cents'],
        ];
    }

    public function getCannibalization(int $teamId): array
    {
        // Find keywords where multiple own URLs rank
        $keywords = SeoKeyword::where('team_id', $teamId)
            ->whereHas('urls', function ($q) use ($teamId) {
                $q->where('seo_urls.team_id', $teamId)
                    ->where('seo_urls.is_own', true)
                    ->whereNotNull('seo_url_keywords.position');
            })
            ->with(['urls' => function ($q) use ($teamId) {
                $q->where('seo_urls.team_id', $teamId)
                    ->where('seo_urls.is_own', true)
                    ->whereNotNull('seo_url_keywords.position');
            }])
            ->get();

        $cannibalization = [];
        foreach ($keywords as $keyword) {
            if ($keyword->urls->count() >= 2) {
                $cannibalization[] = [
                    'keyword' => $keyword->keyword,
                    'search_volume' => $keyword->search_volume,
                    'urls' => $keyword->urls->map(fn ($url) => [
                        'url' => $url->url,
                        'position' => $url->pivot->position,
                    ])->sortBy('position')->values()->toArray(),
                ];
            }
        }

        return $cannibalization;
    }

    public function getVisibilitySummary(int $teamId, ?string $domain = null): array
    {
        $query = SeoUrl::where('team_id', $teamId)
            ->where('is_own', true)
            ->where('status', 'active');

        if ($domain) {
            $query->where('domain', $domain);
        }

        $urls = $query->get();

        return [
            'visibility_score' => $urls->sum('visibility_score'),
            'total_urls' => $urls->count(),
            'total_keywords' => $urls->sum('keyword_count'),
            'total_search_volume' => $urls->sum('total_search_volume'),
            'position_distribution' => $this->calculatePositionDistribution($urls),
        ];
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

    protected function attachKeywordsToUrl(int $teamId, SeoUrl $seoUrl, array $keywords): void
    {
        foreach ($keywords as $kw) {
            $keywordText = is_string($kw) ? $kw : ($kw['keyword'] ?? null);
            if (! $keywordText) {
                continue;
            }

            $keyword = SeoKeyword::firstOrCreate(
                ['team_id' => $teamId, 'keyword' => strtolower(trim($keywordText))],
                [
                    'search_intent' => is_array($kw) ? ($kw['search_intent'] ?? null) : null,
                    'topic' => is_array($kw) ? ($kw['topic'] ?? null) : null,
                ],
            );

            $seoUrl->keywords()->syncWithoutDetaching([$keyword->id]);
        }
    }

    protected function formatUrlData(SeoUrl $seoUrl): array
    {
        return [
            'id' => $seoUrl->id,
            'uuid' => $seoUrl->uuid,
            'url' => $seoUrl->url,
            'domain' => $seoUrl->domain,
            'path' => $seoUrl->path,
            'is_own' => $seoUrl->is_own,
            'status' => $seoUrl->status,
            'http_status' => $seoUrl->http_status,
            'priority' => $seoUrl->priority,
            'keyword_count' => $seoUrl->keyword_count,
            'total_search_volume' => $seoUrl->total_search_volume,
            'backlink_count' => $seoUrl->backlink_count,
            'visibility_score' => (float) $seoUrl->visibility_score,
            'last_crawled_at' => $seoUrl->last_crawled_at?->toIso8601String(),
            'on_page' => $seoUrl->onPage ? [
                'title' => $seoUrl->onPage->title,
                'meta_description' => $seoUrl->onPage->meta_description,
                'h1' => $seoUrl->onPage->h1,
                'word_count' => $seoUrl->onPage->word_count,
                'overall_score' => $seoUrl->onPage->overall_score,
            ] : null,
            'top_keywords' => $seoUrl->keywords->sortByDesc('pivot.position')
                ->take(10)
                ->map(fn ($kw) => [
                    'keyword' => $kw->keyword,
                    'position' => $kw->pivot->position,
                    'search_volume' => $kw->search_volume,
                ])->values()->toArray(),
        ];
    }

    protected function calculatePositionDistribution(Collection $urls): array
    {
        $distribution = [
            '1-3' => 0,
            '4-10' => 0,
            '11-20' => 0,
            '21-50' => 0,
            '51-100' => 0,
        ];

        foreach ($urls as $url) {
            $keywords = $url->keywords;
            foreach ($keywords as $keyword) {
                $pos = $keyword->pivot->position;
                if ($pos === null) {
                    continue;
                }
                if ($pos <= 3) {
                    $distribution['1-3']++;
                } elseif ($pos <= 10) {
                    $distribution['4-10']++;
                } elseif ($pos <= 20) {
                    $distribution['11-20']++;
                } elseif ($pos <= 50) {
                    $distribution['21-50']++;
                } else {
                    $distribution['51-100']++;
                }
            }
        }

        return $distribution;
    }
}
