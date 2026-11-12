<?php

namespace Platform\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Platform\Core\Models\User;
use Platform\Seo\Services\SeoKeywordService;

/**
 * Hintergrund-Wettbewerber-Abruf (1 SERP-/advanced-Call je Keyword, bis 90 s).
 * Synchron riss schon ein 10-Keyword-Lauf das Gateway-Timeout, rechnete aber
 * serverseitig weiter. Status + Ergebnis liegen im Cache unter statusKey().
 */
class FetchSerpCompetitorsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const TIMEOUT = 1200;

    public int $timeout = self::TIMEOUT;

    public int $tries = 1;

    public function __construct(
        public int $teamId,
        public ?int $userId,
        public array $options,
    ) {}

    public static function statusKey(int $teamId): string
    {
        return 'seo:serp-competitors-fetch:' . $teamId;
    }

    public static function markStatus(int $teamId, string $status, array $data = []): void
    {
        Cache::put(self::statusKey($teamId), [
            'status' => $status,
            'updated_at' => now()->toIso8601String(),
        ] + $data, now()->addDays(2));
    }

    public function handle(SeoKeywordService $service): void
    {
        $user = $this->userId ? User::find($this->userId) : null;
        $result = $service->fetchRankings($this->teamId, $user, $this->options);

        self::markStatus($this->teamId, empty($result['error']) ? 'completed' : 'failed', ['result' => $result]);
    }

    public function failed(\Throwable $e): void
    {
        self::markStatus($this->teamId, 'failed', ['error' => $e->getMessage()]);
    }
}
