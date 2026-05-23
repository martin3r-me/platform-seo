<?php

namespace Platform\Seo\Services;

use Carbon\Carbon;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordPosition;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

class SeoSignalService
{
    /**
     * Detect signals for a team based on keyword data changes.
     */
    public function detectSignals(SeoTeamSettings $settings): array
    {
        $today = Carbon::today();
        $totalSpikes = 0;
        $totalDrops = 0;
        $totalPositionRises = 0;
        $totalPositionDrops = 0;
        $totalOpportunities = 0;

        $keywords = SeoKeyword::where('team_id', $settings->team_id)->get();

        $spikeThreshold = config('seo.signals.volume_spike_threshold', 1.0);
        $dropThreshold = config('seo.signals.volume_drop_threshold', -0.5);
        $positionRiseThreshold = config('seo.signals.position_rise_threshold', 5);
        $positionDropThreshold = config('seo.signals.position_drop_threshold', -5);
        $opportunityMinVolume = config('seo.signals.opportunity_min_volume', 200);

        foreach ($keywords as $keyword) {
            // Volume spike/drop detection via dataforseo_raw previous data
            if ($keyword->search_volume && $keyword->dataforseo_raw) {
                $previousVolume = $keyword->dataforseo_raw['previous_search_volume'] ?? null;

                if ($previousVolume !== null && $previousVolume > 0) {
                    $change = ($keyword->search_volume - $previousVolume) / $previousVolume;

                    if ($change >= $spikeThreshold) {
                        $totalSpikes += $this->createKeywordSignal($settings->team_id, $keyword, [
                            'signal_type' => 'volume_spike',
                            'severity' => 'watch',
                            'title' => "Volume Spike: \"{$keyword->keyword}\" {$previousVolume} → {$keyword->search_volume}",
                            'description' => "Suchvolumen hat sich deutlich erhöht (+{$this->formatPercent($change)}).",
                            'metric_before' => $previousVolume,
                            'metric_after' => $keyword->search_volume,
                            'metric_delta' => $keyword->search_volume - $previousVolume,
                            'detected_at' => $today,
                        ]);
                    }

                    if ($change <= $dropThreshold) {
                        $totalDrops += $this->createKeywordSignal($settings->team_id, $keyword, [
                            'signal_type' => 'volume_drop',
                            'severity' => 'warning',
                            'title' => "Volume Drop: \"{$keyword->keyword}\" {$previousVolume} → {$keyword->search_volume}",
                            'description' => "Suchvolumen ist deutlich gefallen ({$this->formatPercent($change)}).",
                            'metric_before' => $previousVolume,
                            'metric_after' => $keyword->search_volume,
                            'metric_delta' => $keyword->search_volume - $previousVolume,
                            'detected_at' => $today,
                        ]);
                    }
                }
            }

            // Position change detection
            $latestPositions = SeoKeywordPosition::where('keyword_id', $keyword->id)
                ->orderByDesc('tracked_at')
                ->take(2)
                ->get();

            if ($latestPositions->count() === 2) {
                $current = $latestPositions->first();
                $previous = $latestPositions->last();
                $positionDelta = $previous->position - $current->position; // positive = improved

                if ($positionDelta >= $positionRiseThreshold) {
                    $totalPositionRises += $this->createKeywordSignal($settings->team_id, $keyword, [
                        'signal_type' => 'position_rise',
                        'severity' => 'info',
                        'title' => "Ranking-Anstieg: \"{$keyword->keyword}\" Pos. {$previous->position} → {$current->position}",
                        'description' => "Position um {$positionDelta} Plätze verbessert.",
                        'metric_before' => $previous->position,
                        'metric_after' => $current->position,
                        'metric_delta' => $positionDelta,
                        'detected_at' => $today,
                    ]);
                }

                if ($positionDelta <= $positionDropThreshold) {
                    $totalPositionDrops += $this->createKeywordSignal($settings->team_id, $keyword, [
                        'signal_type' => 'position_drop',
                        'severity' => 'warning',
                        'title' => "Ranking-Verlust: \"{$keyword->keyword}\" Pos. {$previous->position} → {$current->position}",
                        'description' => "Position um " . abs($positionDelta) . " Plätze verschlechtert.",
                        'metric_before' => $previous->position,
                        'metric_after' => $current->position,
                        'metric_delta' => $positionDelta,
                        'detected_at' => $today,
                    ]);
                }
            }

            // Keyword opportunity: high volume, no ranking via URL pivot
            $hasRanking = $keyword->urls()
                ->wherePivotNotNull('position')
                ->exists();

            if (
                ($keyword->search_volume ?? 0) >= $opportunityMinVolume
                && !$hasRanking
            ) {
                $totalOpportunities += $this->createKeywordSignal($settings->team_id, $keyword, [
                    'signal_type' => 'keyword_opportunity',
                    'severity' => 'info',
                    'title' => "Keyword Opportunity: \"{$keyword->keyword}\" ({$keyword->search_volume} Searches)",
                    'description' => "Keyword hat {$keyword->search_volume} monatliche Suchen, aber kein Ranking.",
                    'metric_before' => 0,
                    'metric_after' => $keyword->search_volume,
                    'metric_delta' => $keyword->search_volume,
                    'detected_at' => $today,
                ]);
            }
        }

        return [
            'volume_spikes' => $totalSpikes,
            'volume_drops' => $totalDrops,
            'position_rises' => $totalPositionRises,
            'position_drops' => $totalPositionDrops,
            'opportunities' => $totalOpportunities,
            'total_signals' => $totalSpikes + $totalDrops + $totalPositionRises + $totalPositionDrops + $totalOpportunities,
        ];
    }

    public function acknowledge(SeoSignal $signal): void
    {
        $signal->update(['status' => 'acknowledged']);
    }

    public function resolve(SeoSignal $signal): void
    {
        $signal->update(['status' => 'resolved']);
    }

    /**
     * Detect URL-level signals (redirect, error, on-page regression, backlink loss).
     */
    public function detectUrlSignals(SeoTeamSettings $settings): array
    {
        $today = Carbon::today();
        $teamId = $settings->team_id;
        $signals = [
            'redirect_detected' => 0,
            'url_error' => 0,
            'cannibalization_detected' => 0,
        ];

        $urls = SeoUrl::where('team_id', $teamId)->where('is_own', true)->get();

        foreach ($urls as $url) {
            // Redirect detection
            if ($url->status === 'redirected' && $url->redirect_url) {
                $signals['redirect_detected'] += $this->createSignalForUrl($teamId, $url, [
                    'signal_type' => 'redirect_detected',
                    'severity' => 'warning',
                    'title' => "Redirect: {$url->url}",
                    'description' => "URL leitet weiter zu {$url->redirect_url}.",
                    'detected_at' => $today,
                ]);
            }

            // URL error detection
            if ($url->status === 'error' && $url->http_status) {
                $signals['url_error'] += $this->createSignalForUrl($teamId, $url, [
                    'signal_type' => 'url_error',
                    'severity' => $url->http_status >= 500 ? 'critical' : 'warning',
                    'title' => "URL Fehler {$url->http_status}: {$url->url}",
                    'description' => "HTTP Status {$url->http_status} fuer {$url->url}.",
                    'metric_after' => $url->http_status,
                    'detected_at' => $today,
                ]);
            }
        }

        // Cannibalization detection
        $cannibalization = app(SeoUrlService::class)->getCannibalization($teamId);
        foreach ($cannibalization as $entry) {
            $urlTexts = implode(', ', array_column($entry['urls'], 'url'));
            $signals['cannibalization_detected'] += $this->createSignal($teamId, [
                'signal_type' => 'cannibalization_detected',
                'severity' => 'watch',
                'title' => "Kannibalisierung: \"{$entry['keyword']}\"",
                'description' => "Mehrere eigene URLs ranken fuer \"{$entry['keyword']}\": {$urlTexts}",
                'detected_at' => $today,
            ]);
        }

        return $signals;
    }

    /**
     * Generic signal creation.
     */
    public function createSignal(int $teamId, array $data): int
    {
        $exists = SeoSignal::where('signal_type', $data['signal_type'])
            ->where('team_id', $teamId)
            ->where('detected_at', $data['detected_at'] ?? Carbon::today())
            ->when(isset($data['keyword_id']), fn ($q) => $q->where('keyword_id', $data['keyword_id']))
            ->when(isset($data['url_id']), fn ($q) => $q->where('url_id', $data['url_id']))
            ->when(!isset($data['keyword_id']) && !isset($data['url_id']), fn ($q) => $q->whereNull('keyword_id')->whereNull('url_id'))
            ->exists();

        if ($exists) {
            return 0;
        }

        SeoSignal::create(array_merge([
            'team_id' => $teamId,
            'detected_at' => Carbon::today(),
        ], $data));

        return 1;
    }

    /**
     * Create a signal linked to a URL.
     */
    public function createSignalForUrl(int $teamId, SeoUrl $url, array $data): int
    {
        $exists = SeoSignal::where('signal_type', $data['signal_type'])
            ->where('url_id', $url->id)
            ->where('detected_at', $data['detected_at'])
            ->exists();

        if ($exists) {
            return 0;
        }

        SeoSignal::create(array_merge($data, [
            'team_id' => $teamId,
            'url_id' => $url->id,
        ]));

        return 1;
    }

    protected function createKeywordSignal(int $teamId, SeoKeyword $keyword, array $data): int
    {
        $exists = SeoSignal::where('signal_type', $data['signal_type'])
            ->where('keyword_id', $keyword->id)
            ->where('detected_at', $data['detected_at'])
            ->exists();

        if ($exists) {
            return 0;
        }

        SeoSignal::create(array_merge($data, [
            'team_id' => $teamId,
            'keyword_id' => $keyword->id,
        ]));

        return 1;
    }

    protected function formatPercent(float $ratio): string
    {
        return round($ratio * 100) . '%';
    }
}
