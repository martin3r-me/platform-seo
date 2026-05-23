<?php

namespace Platform\Seo\Contracts;

use Illuminate\Support\Collection;
use Platform\Seo\Models\SeoTeamSettings;

interface SeoCollectorInterface
{
    /**
     * Unique key for this collector (e.g. 'keyword_metrics').
     */
    public function key(): string;

    /**
     * Human-readable name.
     */
    public function name(): string;

    /**
     * Estimate cost in cents for processing these URLs.
     */
    public function estimateCost(Collection $urls): int;

    /**
     * Filter URLs that are due for a refresh.
     */
    public function urlsDueForRefresh(Collection $urls): Collection;

    /**
     * Base refresh interval in hours.
     */
    public function refreshIntervalHours(): int;

    /**
     * Collect/fetch data for the given URLs.
     *
     * @return array{processed: int, cost_cents: int, errors: array}
     */
    public function collect(SeoTeamSettings $settings, Collection $urls): array;

    /**
     * Whether this collector is enabled.
     */
    public function isEnabled(): bool;

    /**
     * Execution order (lower = earlier).
     */
    public function order(): int;
}
