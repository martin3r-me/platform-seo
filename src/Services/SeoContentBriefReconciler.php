<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\Http;
use Platform\Seo\Models\SeoContentBrief;

/**
 * Schließt den SEO ↔ Flynk-Loop (docs/CONTENT-BRIEF-TRACKING.md, Mechanismus B).
 *
 * Holt die (erwartete) Seite eines Briefs per leichtem HTTP-Fetch — kein
 * DataForSeo-Cost — und liest den Provenance-Marker
 *   <meta name="x-content-brief" content="{brief-uuid}">
 * aus dem <head>. Stimmt die UUID, gilt der Brief als veröffentlicht: Status
 * "published", published_url gesetzt, und die Seite wird als eigene, getrackte
 * seo_url registriert (der Loop schließt sich, Tracking läuft auf der Live-Seite).
 *
 * Bewusst crawl-/marker-basiert: übersteht Slug-Änderungen und braucht keine
 * aktive Rückmeldung von Flynk.
 */
class SeoContentBriefReconciler
{
    public function __construct(
        protected SeoUrlService $urlService,
    ) {}

    /**
     * @return array{checked:int, published:int, pending:int, errors:array<int,string>, details:array<int,array>}
     */
    public function reconcileTeam(int $teamId, bool $dryRun = false): array
    {
        $briefs = SeoContentBrief::where('team_id', $teamId)
            ->where('status', '!=', SeoContentBrief::STATUS_PUBLISHED)
            ->whereNotNull('target_url')
            ->get();

        $checked = 0;
        $published = 0;
        $pending = 0;
        $errors = [];
        $details = [];

        foreach ($briefs as $brief) {
            // Kandidaten-URLs: bevorzugt eine bereits bekannte published_url, sonst die Ziel-URL.
            $candidates = array_values(array_unique(array_filter([
                $brief->published_url,
                $brief->target_url,
            ])));

            $matchedUrl = null;
            foreach ($candidates as $candidate) {
                $checked++;
                try {
                    $response = Http::timeout(10)
                        ->withHeaders(['User-Agent' => 'BHG-SEO-BriefReconciler/1.0'])
                        ->get($candidate);
                } catch (\Throwable $e) {
                    $errors[] = "Brief #{$brief->id} ({$candidate}): {$e->getMessage()}";
                    continue;
                }

                if (!$response->successful()) {
                    continue;
                }

                if ($this->markerMatches($response->body(), $brief->uuid)) {
                    // Bei Redirect die finale URL bevorzugen (defensiv — effectiveUri()
                    // ist nicht in jeder Laravel-Version vorhanden).
                    $matchedUrl = $candidate;
                    if (method_exists($response, 'effectiveUri') && $response->effectiveUri()) {
                        $matchedUrl = (string) $response->effectiveUri();
                    }
                    break;
                }
            }

            if (!$matchedUrl) {
                $pending++;
                continue;
            }

            $details[] = ['brief_id' => $brief->id, 'uuid' => $brief->uuid, 'published_url' => $matchedUrl];

            if ($dryRun) {
                $published++;
                continue;
            }

            // Seite als eigene, getrackte URL registrieren (idempotent).
            $urlId = null;
            try {
                $reg = $this->urlService->register($teamId, $matchedUrl, [
                    'is_own' => true,
                    'reason' => 'content_published',
                ]);
                $urlId = $reg['url_id'] ?? null;
            } catch (\Throwable $e) {
                $errors[] = "Brief #{$brief->id}: URL-Registrierung fehlgeschlagen: {$e->getMessage()}";
            }

            $brief->update([
                'status' => SeoContentBrief::STATUS_PUBLISHED,
                'published_url' => $matchedUrl,
                'published_at' => now(),
            ]);

            $published++;
            $details[array_key_last($details)]['url_id'] = $urlId;
        }

        return [
            'checked' => $checked,
            'published' => $published,
            'pending' => $pending,
            'errors' => $errors,
            'details' => $details,
        ];
    }

    /**
     * Sucht im HTML nach <meta name="x-content-brief" content="{uuid}"> — tolerant
     * gegenüber Attribut-Reihenfolge und Anführungszeichen.
     */
    protected function markerMatches(string $html, string $uuid): bool
    {
        $marker = SeoContentBrief::MARKER_META_NAME;

        if (!preg_match_all('/<meta\b[^>]*>/i', $html, $matches)) {
            return false;
        }

        foreach ($matches[0] as $tag) {
            if (stripos($tag, $marker) === false) {
                continue;
            }
            if (preg_match('/content\s*=\s*["\']([^"\']+)["\']/i', $tag, $cm)
                && strcasecmp(trim($cm[1]), $uuid) === 0) {
                return true;
            }
        }

        return false;
    }
}
