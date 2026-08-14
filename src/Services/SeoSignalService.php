<?php

namespace Platform\Seo\Services;

use Carbon\Carbon;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoUrl;

/**
 * Signal-Lifecycle (quittieren/erledigen) + generische Signal-Erzeugung.
 *
 * Die alten hardcoded Detection-Methoden (Volume/Position/Opportunity/Redirect/
 * URL-Error/Cannibalization) wurden durch das definition-getriebene, gesteuerte
 * System (SeoSignalEvaluator) ersetzt und entfernt. docs/SIGNALS-CONCEPT.md.
 */
class SeoSignalService
{
    public function acknowledge(SeoSignal $signal): void
    {
        $signal->update(['status' => 'acknowledged']);
    }

    public function resolve(SeoSignal $signal): void
    {
        $signal->update(['status' => 'resolved']);
    }

    /** Generische Signal-Erzeugung (dedupt auf Typ + Tag + Ziel). */
    public function createSignal(int $teamId, array $data): int
    {
        $exists = SeoSignal::where('signal_type', $data['signal_type'])
            ->where('team_id', $teamId)
            ->where('detected_at', $data['detected_at'] ?? Carbon::today())
            ->when(isset($data['keyword_id']), fn ($q) => $q->where('keyword_id', $data['keyword_id']))
            ->when(isset($data['url_id']), fn ($q) => $q->where('url_id', $data['url_id']))
            ->when(! isset($data['keyword_id']) && ! isset($data['url_id']), fn ($q) => $q->whereNull('keyword_id')->whereNull('url_id'))
            ->exists();

        if ($exists) {
            return 0;
        }

        SeoSignal::create(array_merge([
            'team_id' => $teamId,
            'detected_at' => Carbon::today(),
        ], $data));

        return 1;
    }

    /** Signal an eine URL gebunden erzeugen. */
    public function createSignalForUrl(int $teamId, SeoUrl $url, array $data): int
    {
        $exists = SeoSignal::where('signal_type', $data['signal_type'])
            ->where('url_id', $url->id)
            ->where('detected_at', $data['detected_at'])
            ->exists();

        if ($exists) {
            return 0;
        }

        SeoSignal::create(array_merge($data, [
            'team_id' => $teamId,
            'url_id' => $url->id,
        ]));

        return 1;
    }
}
