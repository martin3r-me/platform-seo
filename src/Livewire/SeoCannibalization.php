<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoUrlService;

class SeoCannibalization extends Component
{
    public SeoProject $seoProject;

    public function mount(SeoProject $seoProject)
    {
        $this->seoProject = $seoProject;
    }

    public function render()
    {
        $urlService = app(SeoUrlService::class);
        $cannibalization = $urlService->getCannibalization($this->seoProject->team_id);

        // Sort by URL count descending, then by search volume
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
}
