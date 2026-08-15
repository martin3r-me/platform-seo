<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoCostProjectionService;
use Platform\Seo\Services\SeoDataProfileService;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Setzt das Daten-Profil auf alle URLs eines Org-Knotens (Kunde/Engagement) —
 * die betriebliche Steuer-Ebene: „Kunde X arbeiten wir aktiv → dessen Arbeits-
 * URLs auf Standard". Wettbewerber (is_own=false) werden standardmäßig
 * übersprungen (eigene Leiter).
 */
class SetNodeProfileTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.org.profile.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/org/profile - Setzt das Daten-Profil auf alle URLs eines Org-Knotens (Kunde/Engagement, '
            . 'inkl. Unterbaum). Parameter: entity_id (required), profile (required, z.B. basis/standard/tief). '
            . 'Optional: include_competitors (Default false → nur eigene URLs), dry_run. Zeigt die neuen Monatskosten.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'entity_id' => ['type' => 'integer', 'description' => 'Org-Entity (Kunde/Engagement/Initiative)'],
                'profile' => ['type' => 'string', 'description' => 'Profil (eigene: basis/standard/tief; Wettbewerber: beobachten/analyse)'],
                'include_competitors' => ['type' => 'boolean', 'description' => 'Auch Wettbewerber-URLs setzen (Default false)'],
                'dry_run' => ['type' => 'boolean'],
            ],
            'required' => ['entity_id', 'profile'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }
            if (empty($arguments['entity_id']) || empty($arguments['profile'])) {
                return ToolResult::error('entity_id und profile sind erforderlich.', 'VALIDATION_ERROR');
            }

            $entityId = (int) $arguments['entity_id'];
            $profile = (string) $arguments['profile'];
            $includeCompetitors = (bool) ($arguments['include_competitors'] ?? false);
            $dryRun = (bool) ($arguments['dry_run'] ?? false);

            $linker = app(SeoOrganizationLinker::class);
            $profileSvc = app(SeoDataProfileService::class);
            $costSvc = app(SeoCostProjectionService::class);

            // URLs unter dem Knoten (Unterbaum).
            $nodeIds = $linker->workingSetNodeIds($entityId);
            $urlIds = $linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $nodeIds);

            if (empty($urlIds)) {
                return ToolResult::success([
                    'entity_id' => $entityId,
                    'matched' => 0,
                    'message' => 'Keine URLs an diesem Knoten (inkl. Unterbaum).',
                ]);
            }

            $urls = SeoUrl::where('team_id', $team->id)->whereIn('id', $urlIds)->get();

            $set = 0;
            $skipped = 0;
            foreach ($urls as $url) {
                if (!$includeCompetitors && !$url->is_own) {
                    $skipped++;
                    continue;
                }
                if (!$profileSvc->isValidProfile((bool) $url->is_own, $profile)) {
                    $skipped++;
                    continue;
                }
                if (!$dryRun) {
                    $url->update(['data_profile' => $profile]);
                }
                $set++;
            }

            // Kosten nach der Änderung (frische Modelle laden).
            $monthly = $costSvc->urlsMonthlyCents(
                SeoUrl::where('team_id', $team->id)->whereIn('id', $urlIds)->get()
            );

            return ToolResult::success([
                'entity_id' => $entityId,
                'profile' => $profile,
                'matched' => $urls->count(),
                'set' => $set,
                'skipped' => $skipped,
                'dry_run' => $dryRun,
                'monthly_cents' => $monthly,
                'monthly_euro' => number_format($monthly / 100, 2),
                'message' => ($dryRun ? '[dry-run] ' : '') . "{$set} URL(s) auf „{$profile}" gesetzt"
                    . ($skipped ? ", {$skipped} übersprungen" : '')
                    . '. Neue Monatskosten dieser URLs: ' . number_format($monthly / 100, 2) . ' €.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
