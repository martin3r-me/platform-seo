<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;

/**
 * Löscht Cluster explizit per ID — für gezieltes Aufräumen (z. B. Test-Cluster).
 * Dry-Run als Default (zeigt nur, was ginge); erst --force löscht wirklich.
 * Keywords werden nur ABGEHÄNGT (cluster_id = null), nie gelöscht — sie kehren
 * in den ungeordneten Pool zurück. Optionaler --team-Scope als Sicherung.
 */
class DeleteClusters extends Command
{
    protected $signature = 'seo:delete-clusters
                            {ids* : Zu löschende Cluster-IDs}
                            {--team= : Nur innerhalb dieses Teams (Sicherung)}
                            {--force : Wirklich löschen (sonst nur Dry-Run)}';

    protected $description = 'Löscht Cluster per ID (Keywords werden abgehängt, nicht gelöscht).';

    public function handle(): int
    {
        $ids = array_values(array_filter(array_map('intval', (array) $this->argument('ids'))));
        if (empty($ids)) {
            $this->error('Keine IDs angegeben.');

            return self::FAILURE;
        }

        $query = SeoKeywordCluster::whereIn('id', $ids);
        if ($team = $this->option('team')) {
            $query->where('team_id', (int) $team);
        }
        $clusters = $query->get();

        if ($clusters->isEmpty()) {
            $this->warn('Keine passenden Cluster gefunden.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Herkunft', 'Team', 'Keywords'],
            $clusters->map(fn ($c) => [
                $c->id,
                $c->name,
                $c->origin,
                $c->team_id,
                SeoKeyword::where('cluster_id', $c->id)->count(),
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->info('Dry-Run — nichts gelöscht. Mit --force wirklich löschen.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $detached = 0;
        foreach ($clusters as $c) {
            $detached += SeoKeyword::where('cluster_id', $c->id)->update(['cluster_id' => null]);
            $c->snapshots()->delete();
            $c->delete();
            $deleted++;
        }

        $this->info("{$deleted} Cluster gelöscht · {$detached} Keywords abgehängt (in den Pool zurück, nicht gelöscht).");

        return self::SUCCESS;
    }
}
