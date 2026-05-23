<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlSnapshot;

class SnapshotUrls extends Command
{
    protected $signature = 'seo:snapshot-urls
                            {--team= : Specific team ID}';

    protected $description = 'Create daily snapshots of URL metrics for time-series tracking';

    public function handle(): int
    {
        $teamId = $this->option('team');
        $today = now()->toDateString();

        $query = SeoTeamSettings::query();
        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $settingsList = $query->get();

        if ($settingsList->isEmpty()) {
            $this->info('Keine Teams gefunden.');

            return self::SUCCESS;
        }

        $this->info("Erstelle URL-Snapshots fuer {$settingsList->count()} Team(s)...");
        $totalSnapshots = 0;

        foreach ($settingsList as $settings) {
            $this->info("Team ID: {$settings->team_id} | Domain: {$settings->domain}");

            $urls = SeoUrl::where('team_id', $settings->team_id)
                ->where('is_own', true)
                ->where('status', 'active')
                ->get();

            $count = 0;
            foreach ($urls as $url) {
                $keywords = $url->keywords()->get();
                $positionDistribution = $this->buildPositionDistribution($keywords);
                $topKeywords = $keywords->sortBy('pivot.position')
                    ->take(10)
                    ->map(fn ($kw) => [
                        'keyword' => $kw->keyword,
                        'position' => $kw->pivot->position,
                        'search_volume' => $kw->search_volume,
                    ])
                    ->values()
                    ->toArray();

                SeoUrlSnapshot::updateOrCreate(
                    ['url_id' => $url->id, 'snapshot_date' => $today],
                    [
                        'keyword_count' => $url->keyword_count,
                        'total_search_volume' => $url->total_search_volume,
                        'visibility_score' => $url->visibility_score,
                        'backlink_count' => $url->backlink_count,
                        'on_page_score' => $url->onPage?->overall_score,
                        'top_keywords' => $topKeywords,
                        'position_distribution' => $positionDistribution,
                    ],
                );
                $count++;
            }

            $this->line("  {$count} Snapshots erstellt.");
            $totalSnapshots += $count;
        }

        $this->info("Fertig. {$totalSnapshots} Snapshots insgesamt.");

        return self::SUCCESS;
    }

    protected function buildPositionDistribution($keywords): array
    {
        $distribution = ['1-3' => 0, '4-10' => 0, '11-20' => 0, '21-50' => 0, '51+' => 0];

        foreach ($keywords as $kw) {
            $pos = $kw->pivot->position;
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
                $distribution['51+']++;
            }
        }

        return $distribution;
    }
}
