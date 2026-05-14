<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoBudgetLog extends Model
{
    protected $table = 'seo_budget_logs';

    protected $fillable = [
        'project_id',
        'user_id',
        'action',
        'keyword_count',
        'cost_cents',
    ];

    protected $casts = [
        'keyword_count' => 'integer',
        'cost_cents' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'project_id');
    }
}
