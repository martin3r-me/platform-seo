<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoRecommendationService;

/**
 * Erzeugt die SEO-Handlungsempfehlungen (Strategie-Engine, P4) je Team aus dem
 * aktuellen Datenbestand. Reine DB-Analyse, keine API-Kosten.
 */
class GenerateRecommendations extends Command
{
    protected $signature = 'seo:generate-recommendations
                            {--team= : Nur ein bestimmtes Team}';

    protected $description = 'Leitet typisierte Handlungsempfehlungen aus den konsolidierten SEO-Daten ab';

    public function handle(SeoRecommendationService $service): int
    {
        $query = SeoTeamSettings::query();
        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }

        $totals = [
            'expand_url' => 0,
            'build_backlinks' => 0,
            'retire_url' => 0,
            'create_url' => 0,
            'quick_win' => 0,
            'total' => 0,
        ];

        $teams = 0;
        foreach ($query->get() as $settings) {
            $result = $service->generate($settings);
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + ($result[$key] ?? 0);
            }
            $teams++;
        }

        $this->table(
            ['Empfehlungstyp', 'Neu'],
            [
                ['URL ausbauen', $totals['expand_url']],
                ['Backlinks aufbauen', $totals['build_backlinks']],
                ['URL abstellen', $totals['retire_url']],
                ['Neue URL', $totals['create_url']],
                ['Quick Win', $totals['quick_win']],
                ['— Summe —', $totals['total']],
            ],
        );
        $this->info("{$teams} Team(s) analysiert, {$totals['total']} neue Empfehlungen erzeugt.");

        return self::SUCCESS;
    }
}
