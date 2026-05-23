<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Platform\Core\Contracts\HasDisplayName;
use Symfony\Component\Uid\UuidV7;

class SeoProject extends Model implements HasDisplayName
{
    use SoftDeletes;

    protected $table = 'seo_projects';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'description',
        'domain',
        'industry_preset',
        'budget_limit_cents',
        'budget_spent_cents',
        'refresh_interval_hours',
        'next_refresh_at',
        'dataforseo_connection_id',
        'location_code',
        'language_code',
        'clustering_status',
        'clustering_result',
        'settings',
    ];

    protected $casts = [
        'uuid' => 'string',
        'budget_limit_cents' => 'integer',
        'budget_spent_cents' => 'integer',
        'refresh_interval_hours' => 'integer',
        'next_refresh_at' => 'datetime',
        'location_code' => 'integer',
        'language_code' => 'integer',
        'clustering_result' => 'array',
        'settings' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\User::class);
    }

    /**
     * URLs belonging to this project.
     */
    public function urls(): HasMany
    {
        return $this->hasMany(SeoUrl::class, 'project_id');
    }

    /**
     * @deprecated Use urls() and seo_url_keywords instead.
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(SeoKeyword::class, 'seo_project_keyword', 'project_id', 'keyword_id')
            ->withPivot(['position', 'ranked_url', 'target_url', 'content_status', 'priority', 'notes'])
            ->withTimestamps();
    }

    /**
     * @deprecated Alias for backwards compatibility.
     */
    public function directKeywords(): BelongsToMany
    {
        return $this->keywords();
    }

    public function clusters(): HasMany
    {
        return $this->hasMany(SeoKeywordCluster::class, 'project_id')->orderBy('order');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(SeoSignal::class, 'project_id');
    }

    public function budgetLogs(): HasMany
    {
        return $this->hasMany(SeoBudgetLog::class, 'project_id');
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

    public function getDisplayName(): ?string
    {
        return $this->name;
    }
}
