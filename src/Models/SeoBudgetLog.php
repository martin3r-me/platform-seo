<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoBudgetLog extends Model
{
    protected $table = 'seo_budget_logs';

    protected $fillable = [
        'team_id',
        'user_id',
        'action',
        'collector',
        'keyword_count',
        'cost_cents',
    ];

    protected $casts = [
        'keyword_count' => 'integer',
        'cost_cents' => 'integer',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(\Platform\Core\Models\Team::class);
    }
}
