<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Diagnose: zeigt, welche SEO-Records an welchen Organisations-Knoten hängen.
 * Verifikation für die Knoten-Verlinkung (P1) — ohne Test-Harness.
 */
class InspectLinks extends Command
{
    protected $signature = 'seo:inspect-links
                            {--team= : Nur ein bestimmtes Team}
                            {--alias=seo_url : seo_url | seo_cluster | seo_signal}
                            {--limit=50 : Maximale Anzahl Zeilen}';

    protected $description = 'Zeigt die Knoten-Verlinkung von SEO-Records (URLs, Cluster, Signale)';

    private const MAP = [
        'seo_url' => [SeoUrl::class, 'url'],
        'seo_cluster' => [SeoKeywordCluster::class, 'name'],
        'seo_signal' => [SeoSignal::class, 'title'],
    ];

    public function handle(SeoOrganizationLinker $linker): int
    {
        $alias = (string) $this->option('alias');
        if (! isset(self::MAP[$alias])) {
            $this->error("Unbekannter Alias '{$alias}'. Erlaubt: ".implode(', ', array_keys(self::MAP)));
            return self::FAILURE;
        }

        [$modelClass, $labelField] = self::MAP[$alias];
        $limit = max(1, (int) $this->option('limit'));

        $query = $modelClass::query()->orderByDesc('id');
        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }

        $records = $query->limit($limit)->get();
        if ($records->isEmpty()) {
            $this->warn("Keine {$alias}-Records gefunden.");
            return self::SUCCESS;
        }

        $nodesByRecord = $linker->nodesForMany($alias, $records->pluck('id')->all());

        $linked = 0;
        $rows = [];
        foreach ($records as $record) {
            $nodes = $nodesByRecord[$record->id] ?? [];
            if (! empty($nodes)) {
                $linked++;
            }

            $label = (string) ($record->{$labelField} ?? '—');
            if (mb_strlen($label) > 60) {
                $label = mb_substr($label, 0, 57).'…';
            }

            $nodeLabel = empty($nodes)
                ? '—'
                : implode(', ', array_map(
                    fn ($n) => ($n['name'] ?? '?').' (#'.$n['id'].')',
                    $nodes,
                ));

            $rows[] = [$record->id, $label, $nodeLabel];
        }

        $this->table(['ID', $labelField, 'Knoten'], $rows);
        $this->newLine();
        $this->info("{$linked} von {$records->count()} {$alias}-Records sind an einen Knoten gehängt.");

        return self::SUCCESS;
    }
}
