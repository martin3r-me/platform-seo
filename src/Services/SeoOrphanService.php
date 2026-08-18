<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Models\SeoUrlRelationship;

/**
 * Verwaiste URLs — eigene Seiten „ohne Sinn": aktiv, eigenständig (keine
 * Unterseite), aber ohne jedes Zuhause. Kein Wirkungsraum, keine Liste, kein
 * Modul (z. B. Syltjunkie) und kein Org-Knoten hält sie. Solche URLs gehören
 * gekennzeichnet — zuordnen oder aussortieren.
 *
 * Ein „Zuhause" ist eines von:
 *  - Mitglied eines Wirkungsraums (seo_portfolio_urls)
 *  - Mitglied einer Liste (seo_url_list_entries)
 *  - modul-eigen (SeoUrlRegistration.source_module != 'seo')
 *  - an einen Organisations-Knoten gehängt (DimensionLinks)
 *  - Unterseite einer anderen eigenen URL (parent_child)
 */
class SeoOrphanService
{
    public function __construct(private SeoOrganizationLinker $linker) {}

    /**
     * Verwaiste eigene, aktive Root-URLs des Teams.
     *
     * @return int[]
     */
    public function orphanOwnUrlIds(int $teamId): array
    {
        $childIds = SeoUrlRelationship::where('team_id', $teamId)
            ->where('type', 'parent_child')
            ->pluck('target_url_id')->map(fn ($i) => (int) $i)->all();

        $own = SeoUrl::where('team_id', $teamId)
            ->where('status', 'active')
            ->where('is_own', true)
            ->when(! empty($childIds), fn ($q) => $q->whereNotIn('id', $childIds))
            ->pluck('id')->map(fn ($i) => (int) $i)->all();

        if (empty($own)) {
            return [];
        }

        $inModule = SeoUrlRegistration::whereIn('url_id', $own)
            ->where('source_module', '!=', 'seo')
            ->pluck('url_id')->map(fn ($i) => (int) $i)->all();
        $inPortfolio = DB::table('seo_portfolio_urls')->whereIn('url_id', $own)
            ->pluck('url_id')->map(fn ($i) => (int) $i)->all();
        $inList = DB::table('seo_url_list_entries')->whereIn('url_id', $own)
            ->pluck('url_id')->map(fn ($i) => (int) $i)->all();
        $linked = array_map('intval', $this->linker->linkedLinkableIds(SeoOrganizationLinker::ALIAS_URL, $own));

        $homed = array_flip(array_merge($inModule, $inPortfolio, $inList, $linked));

        return array_values(array_filter($own, fn ($id) => ! isset($homed[$id])));
    }

    /** Anzahl verwaister eigener URLs. */
    public function orphanCount(int $teamId): int
    {
        return count($this->orphanOwnUrlIds($teamId));
    }

    /** Ist DIESE URL verwaist (für Badges auf Detail/Liste)? */
    public function isOrphan(SeoUrl $url): bool
    {
        if (! $url->is_own || $url->status !== 'active') {
            return false;
        }

        // Unterseite → hat ein Zuhause (die Eltern-URL).
        if (SeoUrlRelationship::where('type', 'parent_child')->where('target_url_id', $url->id)->exists()) {
            return false;
        }
        if (DB::table('seo_portfolio_urls')->where('url_id', $url->id)->exists()) {
            return false;
        }
        if (DB::table('seo_url_list_entries')->where('url_id', $url->id)->exists()) {
            return false;
        }
        if (SeoUrlRegistration::where('url_id', $url->id)->where('source_module', '!=', 'seo')->exists()) {
            return false;
        }
        if (! empty($this->linker->linkedLinkableIds(SeoOrganizationLinker::ALIAS_URL, [$url->id]))) {
            return false;
        }

        return true;
    }
}
