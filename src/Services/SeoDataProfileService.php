<?php

namespace Platform\Seo\Services;

use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

/**
 * Das Daten-Profil ist der EINE Fetch-Knopf pro URL. Dieser Service löst das
 * wirksame Profil auf und übersetzt es in {Collector => Kadenz(h)}.
 *
 * Präzedenz: explizit auf der URL → Team-Default → Baseline (je is_own).
 * Listen-/Team-Defaults sind Bulk-Setter (schreiben url.data_profile), keine
 * Live-Fallbacks — "Liste/Team setzen, die URL trägt".
 */
class SeoDataProfileService
{
    public function ladderFor(SeoUrl $url): string
    {
        return $url->is_own ? 'own' : 'competitor';
    }

    /** Erlaubte Profile der passenden Leiter. */
    public function availableProfiles(bool $isOwn): array
    {
        return array_keys(config('seo.data_profiles.'.($isOwn ? 'own' : 'competitor'), []));
    }

    public function isValidProfile(bool $isOwn, ?string $profile): bool
    {
        return $profile !== null && in_array($profile, $this->availableProfiles($isOwn), true);
    }

    public function effectiveProfile(SeoUrl $url): string
    {
        $ladder = $this->ladderFor($url);
        $available = $this->availableProfiles($url->is_own);

        if ($url->data_profile && in_array($url->data_profile, $available, true)) {
            return $url->data_profile;
        }

        $teamDefault = optional(SeoTeamSettings::where('team_id', $url->team_id)->first())->default_data_profile;
        if ($teamDefault && in_array($teamDefault, $available, true)) {
            return $teamDefault;
        }

        return config("seo.data_profile_defaults.$ladder", $url->is_own ? 'standard' : 'beobachten');
    }

    /** @return array<string,int> collectorKey => Kadenz in Stunden */
    public function collectors(SeoUrl $url): array
    {
        $ladder = $this->ladderFor($url);
        $profile = $this->effectiveProfile($url);

        return config("seo.data_profiles.$ladder.$profile", []);
    }

    /** Kadenz eines Collectors für diese URL, oder null wenn nicht im Profil. */
    public function collectorCadenceHours(SeoUrl $url, string $collectorKey): ?int
    {
        return $this->collectors($url)[$collectorKey] ?? null;
    }

    /** Wird dieser Collector überhaupt vom Profil gesteuert? (keyword_metrics z.B. nicht) */
    public function isProfileGoverned(string $collectorKey): bool
    {
        return in_array($collectorKey, config('seo.profile_collectors', []), true);
    }
}
