<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Perspektive — die Sicht eines Org-Knotens über seinen ganzen Teilbaum.
 *
 * Der Baum ist der Perspektiv-Wähler (Sidebar), diese Seite ist die Perspektive:
 * ein Knoten samt aller Nachfahren, aggregiert — alle URLs + Werte. Die Wurzel
 * ist damit das Gesamt-Dashboard, jeder Teilbaum eine Kunden-/Bereichs-Sicht.
 */
class SeoPerspective extends Component
{
    use ResolvesTeamSettings;

    public int $entityId;
    public ?string $nodeName = null;

    public function mount(int $entity): void
    {
        $this->resolveSettings();
        $this->entityId = $entity;
        $this->nodeName = app(SeoOrganizationLinker::class)->nodeName($entity);
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;
        $linker = app(SeoOrganizationLinker::class);

        $subtreeIds = $linker->descendantEntityIds($this->entityId);
        $urlIds = $linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $subtreeIds);

        $urls = collect();
        if (! empty($urlIds)) {
            $urls = SeoUrl::where('team_id', $teamId)
                ->whereIn('id', $urlIds)
                ->where('status', 'active')
                ->orderByDesc('visibility_score')
                ->get();

            $ownerByUrl = [];
            foreach (SeoUrlRegistration::whereIn('url_id', $urls->pluck('id'))
                        ->where('source_module', '!=', 'seo')
                        ->get(['url_id', 'source_module']) as $reg) {
                $ownerByUrl[$reg->url_id] ??= $reg->source_module;
            }
            $urls->each(function (SeoUrl $u) use ($ownerByUrl) {
                $u->provenance_key = ! $u->is_own ? 'competitor' : ($ownerByUrl[$u->id] ?? 'seo');
            });
        }

        $own = $urls->where('is_own', true);
        $kpis = [
            'urls' => $urls->count(),
            'own' => $own->count(),
            'competitors' => $urls->count() - $own->count(),
            'nodes' => count($subtreeIds),
            'visibility' => round((float) $own->sum('visibility_score'), 0),
            'keywords' => (int) $own->sum('keyword_count'),
            'search_volume' => (int) $own->sum('total_search_volume'),
            'backlinks' => (int) $own->sum('backlink_count'),
            'visitors' => (int) $own->sum('visitors_30d'),
        ];

        return view('seo::livewire.seo-perspective', [
            'urls' => $urls,
            'kpis' => $kpis,
            'childPerspectives' => $this->childPerspectives($linker),
        ])->layout('platform::layouts.app');
    }

    /**
     * Direkte Kind-Knoten mit URLs im eigenen Teilbaum — zum Weiterdrillen.
     *
     * @return array<int,array{id:int,name:?string,url_count:int}>
     */
    protected function childPerspectives(SeoOrganizationLinker $linker): array
    {
        $class = \Platform\Organization\Models\OrganizationEntity::class;
        if (! class_exists($class)) {
            return [];
        }

        try {
            $children = $class::where('parent_entity_id', $this->entityId)->get(['id', 'name']);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($children as $child) {
            $ids = $linker->descendantEntityIds((int) $child->id);
            $count = count($linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $ids));
            if ($count > 0) {
                $out[] = ['id' => (int) $child->id, 'name' => $child->name, 'url_count' => $count];
            }
        }

        return $out;
    }
}
