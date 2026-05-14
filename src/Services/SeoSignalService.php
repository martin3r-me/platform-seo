<?php

namespace Platform\Seo\Services;

use Carbon\Carbon;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordPosition;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Models\SeoSignal;

class SeoSignalService
{
    /**
     * Detect signals for a project based on keyword data changes.
     */
    public function detectSignals(SeoProject $project): array
    {
        $today = Carbon::today();
        $totalSpikes = 0;
        $totalDrops = 0;
        $totalPositionRises = 0;
        $totalPositionDrops = 0;
        $totalOpportunities = 0;

        $keywords = $project->keywords()->get();

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
                        $totalSpikes += $this->createSignal($project, $keyword, [
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
                        $totalDrops += $this->createSignal($project, $keyword, [
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
                    $totalPositionRises += $this->createSignal($project, $keyword, [
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
                    $totalPositionDrops += $this->createSignal($project, $keyword, [
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

            // Keyword opportunity: high volume, no ranking
            if (
                ($keyword->search_volume ?? 0) >= $opportunityMinVolume
                && $keyword->position === null
                && $keyword->published_url === null
            ) {
                $totalOpportunities += $this->createSignal($project, $keyword, [
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

    protected function createSignal(SeoProject $project, SeoKeyword $keyword, array $data): int
    {
        $exists = SeoSignal::where('signal_type', $data['signal_type'])
            ->where('keyword_id', $keyword->id)
            ->where('detected_at', $data['detected_at'])
            ->exists();

        if ($exists) {
            return 0;
        }

        SeoSignal::create(array_merge($data, [
            'team_id' => $project->team_id,
            'project_id' => $project->id,
            'keyword_id' => $keyword->id,
        ]));

        return 1;
    }

    protected function formatPercent(float $ratio): string
    {
        return round($ratio * 100) . '%';
    }
}
