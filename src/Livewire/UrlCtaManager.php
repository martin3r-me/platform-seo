<?php

namespace Platform\Seo\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoCta;
use Platform\Seo\Models\SeoCtaType;
use Platform\Seo\Models\SeoUrl;

/**
 * Ziel-CTAs je URL pflegen (Modal, Muster wie UrlSeoTarget). Setzt die
 * target-CTAs (Typ aus dem kuratierten Satz + Prominenz + Copy + Ziel), die per
 * Flynk-Push in Produktion gehen. observed (Crawl) wird hier NICHT angefasst.
 */
class UrlCtaManager extends Component
{
    use ResolvesTeamSettings;

    public bool $show = false;

    public ?int $urlId = null;

    public string $urlLabel = '';

    /** @var array<int, array{cta_type_id: ?int, prominence: string, label: string, target: string}> */
    public array $ctas = [];

    public function mount(): void
    {
        $this->resolveSettings();
    }

    #[On('open-url-ctas')]
    public function open(int $urlId): void
    {
        $url = SeoUrl::where('team_id', $this->seoSettings->team_id)->where('id', $urlId)->first();
        if (! $url) {
            return;
        }
        $this->urlId = $url->id;
        $this->urlLabel = $url->display_label;
        $this->loadCtas();
        $this->show = true;
    }

    protected function loadCtas(): void
    {
        $this->ctas = SeoCta::where('url_id', $this->urlId)
            ->where('source', SeoCta::SOURCE_TARGET)
            ->orderByRaw("FIELD(prominence,'primary','secondary','tertiary')")
            ->get()
            ->map(fn (SeoCta $c) => [
                'cta_type_id' => $c->cta_type_id,
                'prominence' => $c->prominence,
                'label' => (string) $c->label,
                'target' => (string) $c->target,
            ])->values()->all();
    }

    public function addCta(): void
    {
        $this->ctas[] = ['cta_type_id' => null, 'prominence' => 'secondary', 'label' => '', 'target' => ''];
    }

    public function removeCta(int $index): void
    {
        if (isset($this->ctas[$index])) {
            unset($this->ctas[$index]);
            $this->ctas = array_values($this->ctas);
        }
    }

    public function save(): void
    {
        if (! $this->urlId) {
            return;
        }
        $teamId = (int) $this->seoSettings->team_id;
        $validTypeIds = SeoCtaType::where('team_id', $teamId)->pluck('id')->all();

        // target-CTAs ersetzen (observed bleibt unberührt).
        SeoCta::where('url_id', $this->urlId)->where('source', SeoCta::SOURCE_TARGET)->delete();

        foreach ($this->ctas as $row) {
            $typeId = (int) ($row['cta_type_id'] ?? 0);
            if (! in_array($typeId, $validTypeIds, true)) {
                continue; // Typ ist Pflicht — Zeilen ohne gültigen Typ fallen raus
            }
            SeoCta::create([
                'url_id' => $this->urlId,
                'team_id' => $teamId,
                'cta_type_id' => $typeId,
                'prominence' => in_array($row['prominence'] ?? '', SeoCta::PROMINENCES, true) ? $row['prominence'] : 'secondary',
                'label' => trim((string) ($row['label'] ?? '')) ?: null,
                'target' => trim((string) ($row['target'] ?? '')) ?: null,
                'source' => SeoCta::SOURCE_TARGET,
            ]);
        }

        $this->show = false;
        $this->dispatch('url-ctas-saved', urlId: $this->urlId);
    }

    public function close(): void
    {
        $this->show = false;
    }

    public function render()
    {
        return view('seo::livewire.url-cta-manager', [
            'ctaTypes' => SeoCtaType::where('team_id', $this->seoSettings->team_id)
                ->where('active', true)->orderBy('sort')->orderBy('label')->get(),
        ]);
    }
}
