<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\SeoKeywordServiceInterface;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;

/**
 * @deprecated Adapter that bridges the old SeoKeywordServiceInterface to the new SeoUrlService.
 *             Used during UI migration transition. Will be removed after UI migration.
 */
class SeoKeywordServiceAdapter implements SeoKeywordServiceInterface
{
    public function __construct(
        protected SeoKeywordService $keywordService,
        protected SeoUrlService $urlService,
    ) {}

    public function createProject(Team $team, User $user, array $data): ?object
    {
        return $this->keywordService->createProject($team, $user, $data);
    }

    public function attachKeywords(int $teamId, int $projectId, array $keywords): array
    {
        return $this->keywordService->attachKeywords($teamId, $projectId, $keywords);
    }

    public function fetchMetrics(int $teamId, ?int $projectId = null, ?User $user = null): array
    {
        return $this->keywordService->fetchMetrics($teamId, $projectId, $user);
    }

    public function fetchRankings(int $teamId, ?User $user = null): array
    {
        return $this->keywordService->fetchRankings($teamId, $user);
    }

    public function getKeywordsForProject(int $teamId): Collection
    {
        return $this->keywordService->getKeywordsForProject($teamId);
    }

    public function getKeywordSummary(int $teamId): array
    {
        return $this->keywordService->getKeywordSummary($teamId);
    }
}
