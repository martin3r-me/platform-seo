<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlBacklink;

/**
 * Backlink data collector.
 *
 * Konsumiert den bestehenden DataForSeoApiService (Integrations-Modul) — kein
 * eigener API-Client. Pro URL wird das Referring-Domain-Profil (ein Backlink je
 * verweisender Domain) via DataForSEO Backlinks-API geholt und in seo_url_backlinks
 * persistiert. Die denormalisierte backlink_count auf der URL wird aus der echten
 * Gesamtzahl (total_count) der API gesetzt.
 */
class BacklinkCollector implements SeoCollectorInterface
{
    /** Max. Referring-Domains, die pro URL gespeichert werden. */
    protected const LIMIT = 1000;

    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
    ) {}

    public function key(): string
    {
        return 'backlinks';
    }

    public function name(): string
    {
        return 'Backlinks';
    }

    public function estimateCost(Collection $urls): int
    {
        $costPerUrl = config('seo.cost_estimates.backlinks', 15);

        return (int) ceil($urls->count() * $costPerUrl);
    }

    public function urlsDueForRefresh(Collection $urls): Collection
    {
        $intervalHours = $this->refreshIntervalHours();

        return $urls->filter(function (SeoUrl $url) use ($intervalHours) {
            return $url->isDueForCollector($this->key(), $intervalHours);
        });
    }

    public function refreshIntervalHours(): int
    {
        return (int) config('seo.refresh_intervals.backlinks', 336);
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $connectionId = $settings->resolveConnectionId();
        if (! $connectionId) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['Keine DataForSEO-Connection für Team']];
        }

        $api = $this->dataForSeoApi->forConnection($connectionId);
        $costPerUrl = (int) config('seo.cost_estimates.backlinks', 15);

        $processed = 0;
        $totalCost = 0;
        $errors = [];

        foreach ($urls as $url) {
            try {
                $result = $api->getBacklinks(null, $url->url, self::LIMIT, 'one_per_domain');
            } catch (\Throwable $e) {
                $errors[] = "{$url->url}: ".$e->getMessage();
                continue;
            }

            foreach ($result['items'] ?? [] as $item) {
                if (($item['type'] ?? 'backlink') !== 'backlink') {
                    continue;
                }

                $sourceUrl = $item['url_from'] ?? null;
                if (! $sourceUrl) {
                    continue;
                }

                SeoUrlBacklink::updateOrCreate(
                    [
                        'url_id' => $url->id,
                        'source_url_hash' => hash('sha256', $sourceUrl),
                    ],
                    [
                        'source_url' => $sourceUrl,
                        'source_domain' => $item['domain_from'] ?? (parse_url($sourceUrl, PHP_URL_HOST) ?? ''),
                        'anchor_text' => $this->truncate($item['anchor'] ?? null, 500),
                        'link_type' => ($item['dofollow'] ?? true) ? 'dofollow' : 'nofollow',
                        'source_domain_authority' => $this->scaleRank($item['domain_from_rank'] ?? null),
                        'first_seen_at' => $this->toDate($item['first_seen'] ?? null),
                        'last_seen_at' => $this->toDate($item['last_seen'] ?? null),
                        'is_active' => ! ($item['is_lost'] ?? false),
                        'meta' => [
                            'rank' => $item['rank'] ?? null,
                            'spam_score' => $item['backlink_spam_score'] ?? null,
                        ],
                    ]
                );
            }

            // Denormalisierte Gesamtzahl aus der echten API-Zählung.
            $url->update([
                'backlink_count' => (int) ($result['total_count'] ?? count($result['items'] ?? [])),
            ]);
            $url->setCollectorTimestamp($this->key());

            $processed++;
            $totalCost += $costPerUrl;
        }

        if (! empty($errors)) {
            Log::warning('SEO: BacklinkCollector Teilfehler', ['errors' => $errors]);
        }

        return ['processed' => $processed, 'cost_cents' => $totalCost, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 30;
    }

    /**
     * DataForSEO domain_from_rank ist 0–1000; die Spalte speichert 0–100.
     */
    protected function scaleRank(mixed $rank): ?int
    {
        if ($rank === null || $rank === '') {
            return null;
        }

        return (int) round(((int) $rank) / 10);
    }

    protected function toDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        return substr($value, 0, 10); // "2021-05-14 12:00:00 +00:00" → "2021-05-14"
    }

    protected function truncate(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr($value, 0, $max);
    }
}
