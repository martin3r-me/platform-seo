<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Services\SeoSignalService;

class UpdateSignalTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.signals.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /seo/signals/{id} - Signal bestätigen oder lösen. Parameter: signal_id (required), action ("acknowledge" oder "resolve").';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'signal_id' => ['type' => 'integer', 'description' => 'ID des Signals'],
                'action' => [
                    'type' => 'string',
                    'enum' => ['acknowledge', 'resolve'],
                    'description' => 'acknowledge: Signal als gesehen markieren. resolve: Signal als gelöst markieren.',
                ],
            ],
            'required' => ['signal_id', 'action'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $signal = SeoSignal::where('team_id', $team->id)
                ->find((int) ($arguments['signal_id'] ?? 0));

            if (!$signal) {
                return ToolResult::error('Signal nicht gefunden.', 'NOT_FOUND');
            }

            $service = app(SeoSignalService::class);
            $action = $arguments['action'] ?? '';

            if ($action === 'acknowledge') {
                $service->acknowledge($signal);
            } elseif ($action === 'resolve') {
                $service->resolve($signal);
            } else {
                return ToolResult::error('Ungültige Aktion. Verwende "acknowledge" oder "resolve".', 'VALIDATION_ERROR');
            }

            return ToolResult::success([
                'id' => $signal->id,
                'status' => $signal->fresh()->status,
                'message' => "Signal '{$signal->title}' " . ($action === 'acknowledge' ? 'bestätigt' : 'gelöst') . '.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
