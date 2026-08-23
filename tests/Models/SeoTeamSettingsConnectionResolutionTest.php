<?php

namespace Platform\Seo\Tests\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Models\Integration;
use Platform\Integrations\Models\IntegrationConnection;
use Platform\Seo\Models\SeoTeamSettings;
use Tests\TestCase;

/**
 * Regression für „Team-Connection-Auflösung gegen Status-Drift härten":
 * eine transient auf 'error' gekippte DataForSEO-Connection darf den
 * Basis-Cluster-Build und die nächtliche seo:pipeline nicht dauerhaft
 * lahmlegen (resolveForTeam blendet 'error' hart aus).
 */
class SeoTeamSettingsConnectionResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_connection_of_team_member_resolves(): void
    {
        [$team, $user, $integration] = $this->teamWithDataForSeoIntegration();

        $connection = $this->makeConnection($integration, $user, 'active');

        $settings = SeoTeamSettings::create(['team_id' => $team->id, 'domain' => 'example.com']);

        $this->assertSame($connection->id, $settings->resolveConnectionId());
    }

    /**
     * Kern der Reproduktion: bevor der Fix da war, lieferte resolveConnectionId()
     * hier null ("Keine DataForSEO-Verbindung im Team"), weil resolveForTeam()
     * 'error'-Connections komplett ausblendet — obwohl es die einzige
     * Connection des Teams ist und sie ggf. nur einmalig gestolpert ist.
     */
    public function test_errored_connection_of_team_member_still_resolves_instead_of_failing_build(): void
    {
        [$team, $user, $integration] = $this->teamWithDataForSeoIntegration();

        $connection = $this->makeConnection($integration, $user, 'error', now()->subHours(2));

        $settings = SeoTeamSettings::create(['team_id' => $team->id, 'domain' => 'example.com']);

        $this->assertSame($connection->id, $settings->resolveConnectionId());
    }

    public function test_stale_errored_connection_still_resolves_but_logs_as_persistent_failure(): void
    {
        [$team, $user, $integration] = $this->teamWithDataForSeoIntegration();

        $connection = $this->makeConnection($integration, $user, 'error', now()->subDays(2));

        $settings = SeoTeamSettings::create(['team_id' => $team->id, 'domain' => 'example.com']);

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'Dauerfehler'));

        $this->assertSame($connection->id, $settings->resolveConnectionId());
    }

    public function test_disabled_connection_does_not_resolve(): void
    {
        [$team, $user, $integration] = $this->teamWithDataForSeoIntegration();

        $this->makeConnection($integration, $user, 'disabled');

        $settings = SeoTeamSettings::create(['team_id' => $team->id, 'domain' => 'example.com']);

        $this->assertNull($settings->resolveConnectionId());
    }

    public function test_explicit_connection_id_wins_regardless_of_status(): void
    {
        [$team, $user, $integration] = $this->teamWithDataForSeoIntegration();

        $connection = $this->makeConnection($integration, $user, 'error');

        $settings = SeoTeamSettings::create([
            'team_id' => $team->id,
            'domain' => 'example.com',
            'dataforseo_connection_id' => $connection->id,
        ]);

        $this->assertSame($connection->id, $settings->resolveConnectionId());
    }

    protected function teamWithDataForSeoIntegration(): array
    {
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $user->teams()->attach($team);

        $integration = Integration::query()->firstOrCreate(
            ['key' => 'dataforseo'],
            ['name' => 'DataForSEO', 'is_enabled' => true],
        );

        return [$team, $user, $integration];
    }

    protected function makeConnection(Integration $integration, User $user, string $status, ?\Illuminate\Support\Carbon $lastTestedAt = null): IntegrationConnection
    {
        return IntegrationConnection::create([
            'integration_id' => $integration->id,
            'owner_user_id' => $user->id,
            'auth_scheme' => 'api_key',
            'status' => $status,
            'last_tested_at' => $lastTestedAt ?? now(),
        ]);
    }
}
