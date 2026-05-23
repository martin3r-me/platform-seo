<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\SeoKeywordServiceInterface;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Seo\Models\SeoProject;

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

    public function fetchRankings(int $projectId, ?User $user = null): array
    {
        return $this->keywordService->fetchRankings($projectId, $user);
    }

    public function getKeywordsForProject(int $projectId): Collection
    {
        return $this->keywordService->getKeywordsForProject($projectId);
    }

    public function getKeywordSummary(int $projectId): array
    {
        return $this->keywordService->getKeywordSummary($projectId);
    }
}
