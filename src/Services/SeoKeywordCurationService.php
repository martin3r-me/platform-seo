<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;

class SeoKeywordCurationService
{
    /**
     * Curate keywords for a team.
     *
     * Two stages:
     * 1. BLACKLIST — rules that exclude keywords
     * 2. WHITELIST — relevance_topics: keywords MUST match at least one topic
     */
    public function curate(SeoTeamSettings $settings, array $options = []): array
    {
        $excludeJobs = $options['exclude_jobs'] ?? true;
        $excludePersons = $options['exclude_persons'] ?? true;
        $excludeLocations = $options['exclude_locations'] ?? true;
        $excludeBrokers = $options['exclude_brokers'] ?? true;
        $excludeNavigational = $options['exclude_navigational'] ?? true;
        $minSearchVolume = $options['min_search_volume'] ?? 0;
        $customExclude = $options['custom_exclude'] ?? [];
        $customInclude = $options['custom_include'] ?? [];
        $relevanceTopics = $options['relevance_topics'] ?? [];
        $dryRun = $options['dry_run'] ?? true;

        $keywords = SeoKeyword::where('team_id', $settings->team_id)->get();

        if ($keywords->isEmpty()) {
            return [
                'total' => 0,
                'keep' => 0,
                'remove' => 0,
                'removed_keywords' => [],
                'kept_keywords' => [],
                'dry_run' => $dryRun,
                'message' => 'Keine Keywords im Projekt.',
            ];
        }

        $includeSet = collect($customInclude)->map(fn ($k) => mb_strtolower(trim($k)));
        $topicTerms = collect($relevanceTopics)->map(fn ($t) => mb_strtolower(trim($t)))->filter();

        $remove = collect();
        $keep = collect();

        foreach ($keywords as $keyword) {
            $kw = mb_strtolower(trim($keyword->keyword));

            if ($includeSet->contains($kw)) {
                $keep->push($this->keywordSummary($keyword, 'protected'));
                continue;
            }

            $reason = $this->checkBlacklistRules(
                $kw, $keyword,
                $excludeJobs, $excludePersons, $excludeLocations,
                $excludeBrokers, $excludeNavigational,
                $minSearchVolume, $customExclude,
            );

            if ($reason) {
                $remove->push($this->keywordSummary($keyword, $reason));
                continue;
            }

            if ($topicTerms->isNotEmpty()) {
                $matchesTopic = $this->matchesAnyTopic($kw, $topicTerms);

                if (!$matchesTopic) {
                    $kd = $keyword->keyword_difficulty ?? 0;
                    $reason = $kd === 0
                        ? 'no_relevance:navigational_query'
                        : 'no_relevance:off_topic';
                    $remove->push($this->keywordSummary($keyword, $reason));
                    continue;
                }
            }

            $keep->push($this->keywordSummary($keyword));
        }

        if (!$dryRun && $remove->isNotEmpty()) {
            $removeIds = $remove->pluck('id')->toArray();
            SeoKeyword::whereIn('id', $removeIds)->delete();
        }

        return [
            'total' => $keywords->count(),
            'keep' => $keep->count(),
            'remove' => $remove->count(),
            'dry_run' => $dryRun,
            'removed_keywords' => $remove->sortBy('keyword')->values()->toArray(),
            'kept_keywords' => $keep->sortByDesc('search_volume')->values()->toArray(),
            'rules_applied' => $this->rulesAppliedSummary($remove),
            'message' => $dryRun
                ? "{$remove->count()} von {$keywords->count()} Keywords würden entfernt. Erneut mit dry_run=false aufrufen um zu löschen."
                : "{$remove->count()} Keywords gelöscht, {$keep->count()} behalten.",
        ];
    }

    protected function checkBlacklistRules(
        string $kw,
        SeoKeyword $keyword,
        bool $excludeJobs,
        bool $excludePersons,
        bool $excludeLocations,
        bool $excludeBrokers,
        bool $excludeNavigational,
        int $minSearchVolume,
        array $customExclude,
    ): ?string {
        if ($minSearchVolume > 0 && ($keyword->search_volume ?? 0) < $minSearchVolume) {
            return 'low_search_volume';
        }

        if ($excludeJobs) {
            foreach (config('seo.curation.job_patterns', []) as $pattern) {
                if (Str::contains($kw, $pattern)) {
                    return "job_keyword:{$pattern}";
                }
            }
        }

        if ($excludePersons) {
            foreach (config('seo.curation.person_patterns', []) as $pattern) {
                if (Str::contains($kw, $pattern)) {
                    return "person_name:{$pattern}";
                }
            }
        }

        if ($excludeLocations) {
            foreach (config('seo.curation.local_patterns', []) as $pattern) {
                if (Str::contains($kw, $pattern)) {
                    return "local_search:{$pattern}";
                }
            }
            foreach (config('seo.curation.cities', []) as $city) {
                if (Str::endsWith($kw, " {$city}") || Str::startsWith($kw, "{$city} ")) {
                    return "local_search:{$city}";
                }
            }
        }

        if ($excludeBrokers) {
            foreach (config('seo.curation.broker_patterns', []) as $pattern) {
                if ($this->containsWord($kw, $pattern)) {
                    return "broker_intent:{$pattern}";
                }
            }
        }

        if ($excludeNavigational) {
            foreach (config('seo.curation.navigational_patterns', []) as $pattern) {
                if (Str::contains($kw, $pattern)) {
                    return "navigational:{$pattern}";
                }
            }
        }

        foreach ($customExclude as $pattern) {
            $p = mb_strtolower(trim($pattern));
            if ($p && Str::contains($kw, $p)) {
                return "custom_exclude:{$p}";
            }
        }

        return null;
    }

    protected function matchesAnyTopic(string $kw, Collection $topicTerms): bool
    {
        foreach ($topicTerms as $topic) {
            if (Str::contains($kw, $topic)) {
                return true;
            }
        }

        return false;
    }

    protected function containsWord(string $haystack, string $needle): bool
    {
        if ($haystack === $needle) {
            return true;
        }
        if (Str::startsWith($haystack, $needle . ' ')) {
            return true;
        }
        if (Str::endsWith($haystack, ' ' . $needle)) {
            return true;
        }
        if (Str::contains($haystack, ' ' . $needle . ' ')) {
            return true;
        }

        return false;
    }

    protected function keywordSummary(SeoKeyword $keyword, ?string $reason = null): array
    {
        $data = [
            'id' => $keyword->id,
            'keyword' => $keyword->keyword,
            'search_volume' => $keyword->search_volume,
            'keyword_difficulty' => $keyword->keyword_difficulty,
        ];

        if ($reason) {
            $data['reason'] = $reason;
        }

        return $data;
    }

    protected function rulesAppliedSummary(Collection $removed): array
    {
        return $removed
            ->groupBy(fn ($item) => explode(':', $item['reason'] ?? 'unknown')[0])
            ->map(fn ($group) => $group->count())
            ->sortDesc()
            ->toArray();
    }
}
