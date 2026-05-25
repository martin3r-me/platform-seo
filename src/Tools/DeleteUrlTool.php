<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Models\SeoUrlRelationship;

class DeleteUrlTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.urls.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /seo/urls - Löscht SEO-URLs inkl. aller verknüpften Daten (Keywords-Pivot, Rankings, Relationships, Signale, On-Page, Listen-Einträge). Parameter: url_id (einzelne ID), url_ids (Array von IDs) oder domain (löscht alle URLs einer Domain). Mindestens einer required.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Einzelne URL-ID zum Löschen',
                ],
                'url_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von URL-IDs zum Bulk-Löschen',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Domain — löscht alle URLs dieser Domain',
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

            $query = SeoUrl::where('team_id', $team->id);

            if (! empty($arguments['url_id'])) {
                $query->where('id', (int) $arguments['url_id']);
            } elseif (! empty($arguments['url_ids'])) {
                $query->whereIn('id', array_map('intval', $arguments['url_ids']));
            } elseif (! empty($arguments['domain'])) {
                $query->where('domain', $arguments['domain']);
            } else {
                return ToolResult::error('Mindestens url_id, url_ids oder domain angeben.', 'VALIDATION_ERROR');
            }

            $urls = $query->get();

            if ($urls->isEmpty()) {
                return ToolResult::error('Keine URLs gefunden.', 'NOT_FOUND');
            }

            $urlIds = $urls->pluck('id')->all();
            $count = count($urlIds);

            // Relationships entfernen (beide Richtungen)
            SeoUrlRelationship::where(function ($q) use ($urlIds) {
                $q->whereIn('source_url_id', $urlIds)
                  ->orWhereIn('target_url_id', $urlIds);
            })->delete();

            // Registrierungen entfernen (verhindert Auto-Restore bei re-register)
            SeoUrlRegistration::whereIn('url_id', $urlIds)->delete();

            // URLs hart löschen (forceDelete statt soft-delete)
            foreach ($urls as $url) {
                $url->forceDelete();
            }

            return ToolResult::success([
                'deleted' => $count,
                'url_ids' => $urlIds,
                'message' => "{$count} URL(s) und zugehörige Daten gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
