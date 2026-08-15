<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;

/**
 * Monatskosten der Datenbeschaffung — direkt aus den Profilen abgeleitet:
 * je Collector (Kosten pro Lauf × Läufe/Monat), summiert je URL → Liste → Team.
 * So wird "was kostet das im Monat" auf jeder Ebene ablesbar.
 */
class SeoCostProjectionService
{
    /** ~30 Tage in Stunden — Läufe/Monat = 720 / Kadenz(h). */
    private const HOURS_PER_MONTH = 720;

    public function __construct(
        protected SeoDataProfileService $profiles,
    ) {}

    /** Monatskosten (Cent) einer URL aus ihrem Profil. */
    public function urlMonthlyCents(SeoUrl $url): int
    {
        $cents = 0;
        foreach ($this->urlBreakdown($url) as $line) {
            $cents += $line['monthly_cents'];
        }

        return $cents;
    }

    /**
     * Aufschlüsselung je Collector: [collector, cadence_hours, runs_per_month, unit_cents, monthly_cents].
     *
     * @return array<int,array>
     */
    public function urlBreakdown(SeoUrl $url): array
    {
        $costMap = config('seo.profile_cost_map', []);
        $lines = [];

        foreach ($this->profiles->collectors($url) as $collector => $hours) {
            if ($hours <= 0) {
                continue;
            }
            $costKey = $costMap[$collector] ?? null;
            $unit = $costKey ? (int) config("seo.cost_estimates.$costKey", 0) : 0;
            $runsPerMonth = self::HOURS_PER_MONTH / $hours;
            $lines[] = [
                'collector' => $collector,
                'cadence_hours' => $hours,
                'runs_per_month' => round($runsPerMonth, 2),
                'unit_cents' => $unit,
                'monthly_cents' => (int) round($unit * $runsPerMonth),
            ];
        }

        return $lines;
    }

    public function urlsMonthlyCents(Collection $urls): int
    {
        return (int) $urls->sum(fn (SeoUrl $u) => $this->urlMonthlyCents($u));
    }

    public function listMonthlyCents(SeoUrlList $list): int
    {
        return $this->urlsMonthlyCents($list->urls()->get());
    }

    public function teamMonthlyCents(int $teamId): int
    {
        return (int) SeoUrl::where('team_id', $teamId)->get()
            ->sum(fn (SeoUrl $u) => $this->urlMonthlyCents($u));
    }

    /** Team-Übersicht: Gesamt + Aufteilung eigen/Wettbewerber. */
    public function teamSummary(int $teamId): array
    {
        $own = 0;
        $competitor = 0;
        foreach (SeoUrl::where('team_id', $teamId)->get() as $url) {
            $c = $this->urlMonthlyCents($url);
            if ($url->is_own) {
                $own += $c;
            } else {
                $competitor += $c;
            }
        }

        return [
            'monthly_cents' => $own + $competitor,
            'own_cents' => $own,
            'competitor_cents' => $competitor,
        ];
    }
}
