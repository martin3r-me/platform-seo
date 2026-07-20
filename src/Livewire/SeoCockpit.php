<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Agentur-Cockpit — das Kunden-Portfolio als Startseite der Agentur-Welt.
 *
 * Zeigt alle Kunden (über die Engagement-Ebene) als scannbare Karten mit ihren
 * aggregierten SEO-Kennzahlen. Klick → die Kunden-Perspektive. Plus Ablage-CTA
 * für noch nicht zugeordnete URLs. „Welche Kunden sind gesund, welche brauchen mich."
 */
class SeoCockpit extends Component
{
    use ResolvesTeamSettings;

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;
        $linker = app(SeoOrganizationLinker::class);

        // Root-only: Unterseiten nicht mitzählen.
        $childUrlIds = SeoUrlRelationship::where('team_id', $teamId)
            ->where('type', 'parent_child')
            ->pluck('target_url_id')->all();

        $customerIds = $linker->customerEntityIdsForTeam($teamId);

        $names = [];
        $class = \Platform\Organization\Models\OrganizationEntity::class;
        if (class_exists($class) && ! empty($customerIds)) {
            try {
                foreach ($class::whereIn('id', $customerIds)->get(['id', 'name']) as $e) {
                    $names[(int) $e->id] = $e->name;
                }
            } catch (\Throwable $e) {
                // Organization nicht geladen
            }
        }

        $cards = [];
        foreach ($customerIds as $cid) {
            $subtree = $linker->descendantEntityIds((int) $cid);
            $urlIds = $linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $subtree);

            $urls = collect();
            if (! empty($urlIds)) {
                $urls = SeoUrl::where('team_id', $teamId)
                    ->whereIn('id', $urlIds)
                    ->where('status', 'active')
                    ->where('is_own', true)
                    ->when(! empty($childUrlIds), fn ($q) => $q->whereNotIn('id', $childUrlIds))
                    ->get();
            }

            $cards[] = [
                'id' => (int) $cid,
                'name' => $names[(int) $cid] ?? ('Kunde #'.$cid),
                'urls' => $urls->count(),
                'visibility' => round((float) $urls->sum('visibility_score'), 0),
                'keywords' => (int) $urls->sum('keyword_count'),
                'search_volume' => (int) $urls->sum('total_search_volume'),
            ];
        }

        // Getrackte zuerst (nach Sichtbarkeit), untracked zuletzt.
        usort($cards, fn ($a, $b) => ($b['visibility'] <=> $a['visibility']) ?: ($b['urls'] <=> $a['urls']));

        return view('seo::livewire.seo-cockpit', [
            'cards' => $cards,
            'ablageCount' => $this->ablageCount($teamId, $linker, $childUrlIds),
            'totals' => [
                'customers' => count($cards),
                'tracked' => count(array_filter($cards, fn ($c) => $c['urls'] > 0)),
                'visibility' => array_sum(array_column($cards, 'visibility')),
            ],
        ])->layout('platform::layouts.app');
    }

    /** Anzahl Agentur-URLs ohne Kontext (root-only, nicht modul-eigen). */
    protected function ablageCount(int $teamId, SeoOrganizationLinker $linker, array $childUrlIds): int
    {
        $own = SeoUrl::where('team_id', $teamId)
            ->where('status', 'active')
            ->where('is_own', true)
            ->when(! empty($childUrlIds), fn ($q) => $q->whereNotIn('id', $childUrlIds))
            ->pluck('id')->map(fn ($i) => (int) $i)->all();

        if (empty($own)) {
            return 0;
        }

        $moduleOwned = SeoUrlRegistration::whereIn('url_id', $own)
            ->where('source_module', '!=', 'seo')
            ->pluck('url_id')->map(fn ($i) => (int) $i)->unique()->all();
        $linked = $linker->linkedLinkableIds(SeoOrganizationLinker::ALIAS_URL, $own);
        $exclude = array_flip(array_merge($moduleOwned, $linked));

        return count(array_filter($own, fn ($id) => ! isset($exclude[$id])));
    }
}
