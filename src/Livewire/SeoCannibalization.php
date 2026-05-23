<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Services\SeoUrlService;

class SeoCannibalization extends Component
{
    use ResolvesTeamSettings;

    public function mount()
    {
        $this->resolveSettings();
    }

    public function render()
    {
        $urlService = app(SeoUrlService::class);
        $cannibalization = $urlService->getCannibalization($this->seoSettings->team_id);

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
