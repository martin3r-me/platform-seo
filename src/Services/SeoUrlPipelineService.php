<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

class SeoUrlPipelineService
{
    /** @var SeoCollectorInterface[] */
    protected array $collectors = [];

    public function __construct(
        protected SeoBudgetGuardService $budgetGuard,
    ) {}

    public function registerCollector(SeoCollectorInterface $collector): void
    {
        $this->collectors[$collector->key()] = $collector;
    }

    /**
     * @return SeoCollectorInterface[]
     */
    public function getCollectors(): array
    {
        return $this->collectors;
    }

    public function getCollector(string $key): ?SeoCollectorInterface
    {
        return $this->collectors[$key] ?? null;
    }

    /**
     * Run the full pipeline for a team's settings.
     *
     * @return array{urls_processed: int, collectors_run: array, total_cost_cents: int, errors: array}
     */
    public function runPipeline(SeoTeamSettings $settings, array $options = []): array
    {
        $dryRun = $options['dry_run'] ?? false;
        $force = $options['force'] ?? false;
        $maxUrls = $options['max_urls'] ?? config('seo.pipeline.max_urls_per_run', 50);
        $onlyCollectors = $options['collectors'] ?? [];
        $urlIds = $options['url_ids'] ?? null;

        // Load active URLs, sorted by priority DESC
        $query = SeoUrl::where('team_id', $settings->team_id)
            ->where('status', 'active')
            ->where('is_own', true)
            ->orderByDesc('priority');

        if ($urlIds) {
            $query->whereIn('id', $urlIds);
        } else {
            $query->limit($maxUrls);
        }

        $allUrls = $query->get();

        if ($allUrls->isEmpty()) {
            return [
                'urls_processed' => 0,
                'collectors_run' => [],
                'total_cost_cents' => 0,
                'errors' => [],
            ];
        }

        // Sort collectors by order
        $sortedCollectors = collect($this->collectors)
            ->filter(fn (SeoCollectorInterface $c) => $c->isEnabled())
            ->when(! empty($onlyCollectors), fn ($c) => $c->filter(
                fn (SeoCollectorInterface $collector) => in_array($collector->key(), $onlyCollectors),
            ))
            ->sortBy(fn (SeoCollectorInterface $c) => $c->order());

        $totalCost = 0;
        $collectorsRun = [];
        $allErrors = [];
        $urlsProcessed = 0;
        $maxBudgetPerRun = $this->getMaxBudgetPerRun($settings);

        foreach ($sortedCollectors as $collector) {
            // Filter URLs due for refresh
            $dueUrls = $force ? $allUrls : $collector->urlsDueForRefresh($allUrls);

            if ($dueUrls->isEmpty()) {
                $collectorsRun[] = [
                    'collector' => $collector->key(),
                    'name' => $collector->name(),
                    'urls_due' => 0,
                    'processed' => 0,
                    'cost_cents' => 0,
                    'skipped' => true,
                ];

                continue;
            }

            // Estimate cost
            $estimatedCost = $collector->estimateCost($dueUrls);

            // Budget check
            if (! $dryRun && $maxBudgetPerRun > 0 && ($totalCost + $estimatedCost) > $maxBudgetPerRun) {
                // Reduce URLs to fit budget
                $dueUrls = $this->reduceUrlsToBudget($collector, $dueUrls, $maxBudgetPerRun - $totalCost);
                if ($dueUrls->isEmpty()) {
                    $collectorsRun[] = [
                        'collector' => $collector->key(),
                        'name' => $collector->name(),
                        'urls_due' => 0,
                        'processed' => 0,
                        'cost_cents' => 0,
                        'skipped' => true,
                        'reason' => 'budget_exceeded',
                    ];

                    continue;
                }
            }

            if (! $dryRun && ! $this->budgetGuard->canFetch($settings, $estimatedCost)) {
                $collectorsRun[] = [
                    'collector' => $collector->key(),
                    'name' => $collector->name(),
                    'urls_due' => $dueUrls->count(),
                    'processed' => 0,
                    'cost_cents' => 0,
                    'skipped' => true,
                    'reason' => 'project_budget_exceeded',
                ];

                continue;
            }

            if ($dryRun) {
                $collectorsRun[] = [
                    'collector' => $collector->key(),
                    'name' => $collector->name(),
                    'urls_due' => $dueUrls->count(),
                    'estimated_cost_cents' => $estimatedCost,
                    'dry_run' => true,
                ];
                $totalCost += $estimatedCost;

                continue;
            }

            // Execute collector
            $result = $collector->collect($settings, $dueUrls);

            // Record cost
            if ($result['cost_cents'] > 0) {
                $this->budgetGuard->recordCost(
                    $settings,
                    'pipeline_'.$collector->key(),
                    $result['processed'],
                    $result['cost_cents'],
                    null,
                    $collector->key(),
                );
            }

            $totalCost += $result['cost_cents'];
            $urlsProcessed = max($urlsProcessed, $dueUrls->count());

            $collectorsRun[] = [
                'collector' => $collector->key(),
                'name' => $collector->name(),
                'urls_due' => $dueUrls->count(),
                'processed' => $result['processed'],
                'cost_cents' => $result['cost_cents'],
                'errors' => $result['errors'] ?? [],
            ];

            if (! empty($result['errors'])) {
                $allErrors = array_merge($allErrors, $result['errors']);
            }
        }

        // Check for budget pressure
        if (! $dryRun) {
            $this->checkBudgetPressure($settings);
        }

        return [
            'urls_processed' => $urlsProcessed,
            'collectors_run' => $collectorsRun,
            'total_cost_cents' => $totalCost,
            'errors' => $allErrors,
        ];
    }

    /**
     * Run the pipeline for a single URL.
     */
    public function runForUrl(SeoTeamSettings $settings, SeoUrl $url, array $collectorKeys = [], bool $force = true): array
    {
        return $this->runPipeline($settings, [
            'url_ids' => [$url->id],
            'collectors' => $collectorKeys,
            'force' => $force,
        ]);
    }

    protected function getMaxBudgetPerRun(SeoTeamSettings $settings): int
    {
        if ($settings->budget_limit_cents === null) {
            return 0; // No limit
        }

        $percentage = config('seo.pipeline.max_budget_percentage_per_run', 25);

        return (int) ($settings->budget_limit_cents * $percentage / 100);
    }

    protected function reduceUrlsToBudget(SeoCollectorInterface $collector, Collection $urls, int $remainingBudget): Collection
    {
        if ($remainingBudget <= 0) {
            return collect();
        }

        // Take URLs one by one until we exceed the budget
        $selected = collect();
        foreach ($urls as $url) {
            $cost = $collector->estimateCost(collect([$url]));
            if ($cost <= $remainingBudget) {
                $selected->push($url);
                $remainingBudget -= $cost;
            }
            if ($remainingBudget <= 0) {
                break;
            }
        }

        return $selected;
    }

    protected function checkBudgetPressure(SeoTeamSettings $settings): void
    {
        if ($settings->budget_limit_cents === null) {
            return;
        }

        $threshold = config('seo.pipeline.budget_pressure_threshold', 0.8);
        $settings->refresh();

        if ($settings->budget_spent_cents >= ($settings->budget_limit_cents * $threshold)) {
            // Emit a signal for budget pressure
            app(SeoSignalService::class)->createSignal($settings->team_id, [
                'signal_type' => 'budget_pressure',
                'severity' => 'warning',
                'title' => 'Budget-Warnung',
                'description' => sprintf(
                    'Das SEO-Budget ist zu %.0f%% ausgeschoepft (%d/%d Cents).',
                    ($settings->budget_spent_cents / $settings->budget_limit_cents) * 100,
                    $settings->budget_spent_cents,
                    $settings->budget_limit_cents,
                ),
            ]);
        }
    }
}
