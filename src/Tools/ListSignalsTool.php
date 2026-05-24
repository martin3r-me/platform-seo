<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoSignal;

class ListSignalsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.signals.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/signals - Listet SEO-Signale (Alerts). Optional: status (new/acknowledged/resolved), severity (info/warning/critical), signal_type, limit, offset. Standardmäßig nur offene Signale (new + acknowledged).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'status' => [
                    'type' => 'string',
                    'enum' => ['new', 'acknowledged', 'resolved'],
                    'description' => 'Filter nach Status. Ohne: new + acknowledged',
                ],
                'severity' => [
                    'type' => 'string',
                    'enum' => ['info', 'warning', 'critical'],
                ],
                'signal_type' => [
                    'type' => 'string',
                    'description' => 'Filter nach Signal-Typ (z.B. ranking_drop, volume_spike)',
                ],
                'limit' => ['type' => 'integer'],
                'offset' => ['type' => 'integer'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $query = SeoSignal::where('team_id', $team->id)
                ->with(['keyword', 'url']);

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            } else {
                $query->whereIn('status', ['new', 'acknowledged']);
            }

            if (!empty($arguments['severity'])) {
                $query->where('severity', $arguments['severity']);
            }
            if (!empty($arguments['signal_type'])) {
                $query->where('signal_type', $arguments['signal_type']);
            }

            $query->orderByDesc('detected_at');

            $limit = min((int) ($arguments['limit'] ?? 50), 200);
            $offset = (int) ($arguments['offset'] ?? 0);
            $total = $query->count();

            $signals = $query->skip($offset)->take($limit)->get();

            return ToolResult::success([
                'signals' => $signals->map(fn (SeoSignal $s) => [
                    'id' => $s->id,
                    'uuid' => $s->uuid,
                    'signal_type' => $s->signal_type,
                    'severity' => $s->severity,
                    'status' => $s->status,
                    'title' => $s->title,
                    'description' => $s->description,
                    'metric_before' => $s->metric_before ? (float) $s->metric_before : null,
                    'metric_after' => $s->metric_after ? (float) $s->metric_after : null,
                    'metric_delta' => $s->metric_delta ? (float) $s->metric_delta : null,
                    'keyword' => $s->keyword?->keyword,
                    'keyword_id' => $s->keyword_id,
                    'url' => $s->url?->url,
                    'url_id' => $s->url_id,
                    'detected_at' => $s->detected_at?->toIso8601String(),
                    'context' => $s->context,
                ])->all(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
