<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SeoTeamSettings extends Model
{
    protected $table = 'seo_team_settings';

    protected $fillable = [
        'team_id',
        'domain',
        'budget_limit_cents',
        'budget_spent_cents',
        'refresh_interval_hours',
        'next_refresh_at',
        'default_data_profile',
        'dataforseo_connection_id',
        'location_code',
        'language_code',
        'language_name',
        'default_competitor_tracking_depth',
        'clustering_status',
        'clustering_result',
        'settings',
    ];

    protected $casts = [
        'budget_limit_cents' => 'integer',
        'budget_spent_cents' => 'integer',
        'refresh_interval_hours' => 'integer',
        'next_refresh_at' => 'datetime',
        'location_code' => 'integer',
        'language_code' => 'integer',
        'default_competitor_tracking_depth' => 'integer',
        'clustering_result' => 'array',
        'settings' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function clusters(): HasMany
    {
        return $this->hasMany(SeoKeywordCluster::class, 'team_id', 'team_id')->orderBy('order');
    }

    public function budgetLogs(): HasMany
    {
        return $this->hasMany(SeoBudgetLog::class, 'team_id', 'team_id');
    }

    public function getBudgetRemainingCentsAttribute(): int
    {
        if ($this->budget_limit_cents === null) {
            return PHP_INT_MAX;
        }

        return max(0, $this->budget_limit_cents - $this->budget_spent_cents);
    }

    public function getBudgetPercentageAttribute(): ?float
    {
        if ($this->budget_limit_cents === null || $this->budget_limit_cents === 0) {
            return null;
        }

        return round(($this->budget_spent_cents / $this->budget_limit_cents) * 100, 1);
    }

    /**
     * Löst die DataForSEO Connection-ID auf.
     *
     * 1. Explizit gesetzte connection_id (immer, unabhängig vom Status — bewusst
     *    gepinnt, siehe Doku unten).
     * 2. Aktive Connection über Team-Mitglieder/Team-Shares (resolveForTeam).
     * 3. Fallback: nur-'error'-markierte Connection desselben Teams
     *    (resolveErroredConnectionId) — härtet gegen Status-Drift, siehe dort.
     */
    public function resolveConnectionId(): ?int
    {
        if ($this->dataforseo_connection_id) {
            return $this->dataforseo_connection_id;
        }

        if (! $this->team) {
            return null;
        }

        $resolver = app(\Platform\Integrations\Services\IntegrationConnectionResolver::class);
        $connection = $resolver->resolveForTeam('dataforseo', $this->team);
        if ($connection) {
            return $connection->id;
        }

        return $this->resolveErroredConnectionId();
    }

    /**
     * Fallback, wenn resolveForTeam() nichts liefert: resolveForTeam() blendet
     * 'error'-Connections hart aus — auch wenn nur ein einzelner Testlauf
     * fehlgeschlagen ist. Ohne diesen Fallback stoppt ein transienter
     * DataForSEO-Fehler den Basis-Cluster-Build UND die nächtliche
     * seo:pipeline dauerhaft ("Keine DataForSEO-Verbindung im Team"), obwohl
     * dieselbe Connection im User-Kontext (resolveForUser prüft den Status
     * der eigenen Connection gar nicht erst) anstandslos weiterläuft.
     *
     * Statt die Connection zu verstecken, wird sie hier zurückgegeben — der
     * nächste echte API-Call in DataForSeoApiService testet sie live erneut
     * und setzt status bei Erfolg zurück auf 'active' (Selbstheilung). Bleibt
     * sie länger als eine Pipeline-Kadenz (24h) in 'error', ist das kein
     * Ausrutscher mehr, sondern ein Dauerfehler — der wird laut geloggt statt
     * still verschluckt (Log::error statt Log::warning).
     */
    protected function resolveErroredConnectionId(): ?int
    {
        $integration = \Platform\Integrations\Models\Integration::query()
            ->where('key', 'dataforseo')
            ->first();
        if (! $integration || ! $integration->is_enabled) {
            return null;
        }

        $teamMemberIds = $this->team->users()->pluck('users.id')->toArray();

        $connection = null;
        if (! empty($teamMemberIds)) {
            $connection = \Platform\Integrations\Models\IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->whereIn('owner_user_id', $teamMemberIds)
                ->where('status', 'error')
                ->orderByDesc('is_default')
                ->orderByDesc('last_tested_at')
                ->first();
        }

        if (! $connection) {
            $connection = \Platform\Integrations\Models\IntegrationConnection::query()
                ->where('integration_id', $integration->id)
                ->where('status', 'error')
                ->whereHas('shares', fn ($q) => $q->where('team_id', $this->team_id))
                ->orderByDesc('last_tested_at')
                ->first();
        }

        if (! $connection) {
            return null;
        }

        $context = [
            'team_id' => $this->team_id,
            'connection_id' => $connection->id,
            'last_error' => $connection->last_error,
            'last_tested_at' => $connection->last_tested_at,
        ];

        $isStale = $connection->last_tested_at && $connection->last_tested_at->lt(now()->subDay());
        if ($isStale) {
            \Illuminate\Support\Facades\Log::error('SEO: DataForSEO-Connection seit über 24h in Fehlerstatus — vermutlich Dauerfehler, wird für Re-Test dennoch verwendet', $context);
        } else {
            \Illuminate\Support\Facades\Log::warning('SEO: DataForSEO-Connection in Fehlerstatus — wird für Re-Test erneut verwendet statt Team-Build/Pipeline zu blockieren', $context);
        }

        return $connection->id;
    }

    /**
     * Löst den Sprachnamen für DataForSEO API auf.
     * Gibt den explizit gesetzten language_name zurück, oder den Config-Default.
     */
    public function resolveLanguageName(): ?string
    {
        return $this->language_name ?: config('integrations.dataforseo.default_language_name', 'German');
    }

    public function isRefreshDue(): bool
    {
        if (!$this->refresh_interval_hours) {
            return false;
        }

        if (!$this->next_refresh_at) {
            return true;
        }

        return $this->next_refresh_at->isPast();
    }
}
