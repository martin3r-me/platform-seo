<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlSnapshot;

class SnapshotUrls extends Command
{
    protected $signature = 'seo:snapshot-urls
                            {--project= : Specific project ID}';

    protected $description = 'Create daily snapshots of URL metrics for time-series tracking';

    public function handle(): int
    {
        $projectId = $this->option('project');
        $today = now()->toDateString();

        $query = SeoProject::query();
        if ($projectId) {
            $query->where('id', $projectId);
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->info('Keine Projekte gefunden.');

            return self::SUCCESS;
        }

        $this->info("Erstelle URL-Snapshots fuer {$projects->count()} Projekt(e)...");
        $totalSnapshots = 0;

        foreach ($projects as $project) {
            $this->info("Projekt: {$project->name}");

            $urls = SeoUrl::where('project_id', $project->id)
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
