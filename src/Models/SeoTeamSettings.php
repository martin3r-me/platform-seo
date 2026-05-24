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
        'dataforseo_connection_id',
        'location_code',
        'language_code',
        'language_name',
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
     * 1. Explizit gesetzte connection_id
     * 2. Fallback: über Team-Mitglieder automatisch auflösen
     */
    public function resolveConnectionId(): ?int
    {
        if ($this->dataforseo_connection_id) {
            return $this->dataforseo_connection_id;
        }

        if ($this->team) {
            $resolver = app(\Platform\Integrations\Services\IntegrationConnectionResolver::class);
            $connection = $resolver->resolveForTeam('dataforseo', $this->team);
            if ($connection) {
                return $connection->id;
            }
        }

        return null;
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
