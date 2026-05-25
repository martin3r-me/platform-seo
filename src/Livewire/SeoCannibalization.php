<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoUrlService;

class SeoCannibalization extends Component
{
    use ResolvesTeamSettings;

    public SeoUrlList $seoUrlList;

    public function mount(SeoUrlList $seoUrlList)
    {
        $this->resolveSettings();
        $this->seoUrlList = $seoUrlList;
    }

    public function render()
    {
        $urlService = app(SeoUrlService::class);
        $cannibalization = $urlService->getCannibalization($this->seoSettings->team_id);

        // Filter to only URLs in this list (root + children)
        $listUrlIds = $this->getListUrlIds();

        $cannibalization = array_filter($cannibalization, function ($item) use ($listUrlIds) {
            foreach ($item['urls'] as $urlData) {
                if (isset($urlData['url_id']) && in_array($urlData['url_id'], $listUrlIds)) {
                    return true;
                }
            }

            return false;
        });

        usort($cannibalization, function ($a, $b) {
            $countDiff = count($b['urls']) - count($a['urls']);
            if ($countDiff !== 0) {
                return $countDiff;
            }

            return ($b['search_volume'] ?? 0) - ($a['search_volume'] ?? 0);
        });

        return view('seo::livewire.seo-cannibalization', [
            'cannibalization' => $cannibalization,
        ])->layout('platform::layouts.app');
    }

    private function getListUrlIds(): array
    {
        $rootIds = $this->seoUrlList->urls()->pluck('seo_urls.id');
        $childIds = SeoUrlRelationship::where('type', 'parent_child')
            ->whereIn('source_url_id', $rootIds)
            ->pluck('target_url_id');

        return $rootIds->merge($childIds)->all();
    }
}
