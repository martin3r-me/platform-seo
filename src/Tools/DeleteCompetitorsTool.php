<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoKeywordCompetitor;

/**
 * DELETE /seo/competitors — löscht SERP-Wettbewerber-Einträge
 * (seo_keyword_competitors) gezielt, team-scoped. Für das Aufräumen falscher
 * Abrufe (z. B. google.com als einziger „Wettbewerber"). Mindestens ein Filter
 * ist Pflicht, damit nie versehentlich alle Wettbewerberdaten verschwinden.
 */
class DeleteCompetitorsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.competitors.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /seo/competitors - Löscht SERP-Wettbewerber-Einträge (seo_keyword_competitors), team-scoped. Mindestens ein Filter Pflicht: domain, keyword_ids oder tracked_at. Filter werden UND-verknüpft. Nutze dry_run=true, um vorher Anzahl + Beispiele zu sehen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'domain' => [
                    'type' => 'string',
                    'description' => 'Nur Einträge dieser Wettbewerber-Domain (exakt, z. B. "google.com").',
                ],
                'keyword_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Nur Einträge dieser Keywords.',
                ],
                'tracked_at' => [
                    'type' => 'string',
                    'description' => 'Nur Einträge dieses Abruf-Tags (YYYY-MM-DD).',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Nur zählen + Beispiele zeigen, nichts löschen.',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $domain = trim((string) ($arguments['domain'] ?? ''));
            $keywordIds = array_values(array_filter(array_map('intval', (array) ($arguments['keyword_ids'] ?? []))));
            $trackedAt = trim((string) ($arguments['tracked_at'] ?? ''));

            if ($domain === '' && empty($keywordIds) && $trackedAt === '') {
                return ToolResult::error('Mindestens ein Filter ist erforderlich: domain, keyword_ids oder tracked_at.', 'VALIDATION_ERROR');
            }

            if ($trackedAt !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $trackedAt)) {
                return ToolResult::error('tracked_at muss das Format YYYY-MM-DD haben.', 'VALIDATION_ERROR');
            }

            $query = SeoKeywordCompetitor::where('team_id', $team->id)
                ->when($domain !== '', fn ($q) => $q->where('domain', $domain))
                ->when(! empty($keywordIds), fn ($q) => $q->whereIn('keyword_id', $keywordIds))
                ->when($trackedAt !== '', fn ($q) => $q->whereDate('tracked_at', $trackedAt));

            $count = (clone $query)->count();
            $filters = array_filter([
                'domain' => $domain ?: null,
                'keyword_ids' => $keywordIds ?: null,
                'tracked_at' => $trackedAt ?: null,
            ]);

            if (! empty($arguments['dry_run'])) {
                $sample = (clone $query)->with('keyword:id,keyword')->limit(10)->get()
                    ->map(fn ($c) => [
                        'keyword_id' => $c->keyword_id,
                        'keyword' => $c->keyword?->keyword,
                        'domain' => $c->domain,
                        'url' => $c->url,
                        'position' => $c->position,
                        'tracked_at' => $c->tracked_at?->toDateString(),
                    ])->all();

                return ToolResult::success([
                    'dry_run' => true,
                    'would_delete' => $count,
                    'filters' => $filters,
                    'sample' => $sample,
                    'message' => "{$count} Wettbewerber-Einträge würden gelöscht.",
                ]);
            }

            $deleted = $query->delete();

            return ToolResult::success([
                'deleted' => $deleted,
                'filters' => $filters,
                'message' => "{$deleted} Wettbewerber-Einträge gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
