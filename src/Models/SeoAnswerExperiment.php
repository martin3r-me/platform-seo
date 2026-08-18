<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * Antwort-Experiment (v2, docs/NORDSTERN-v2.md §4) — der Lern-Loop je Antwort:
 * Baseline → Änderung → Ergebnis → Verdict + Learning.
 */
class SeoAnswerExperiment extends Model
{
    protected $table = 'seo_answer_experiments';

    public const STATUS_PLANNED = 'planned';
    public const STATUS_RUNNING = 'running';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'uuid', 'team_id', 'portfolio_id', 'entity_id', 'answer_unit_id', 'measure_id', 'brief_id',
        'hypothesis', 'change_summary', 'status', 'verdict', 'baseline', 'result', 'learning',
        'applied_at', 'measured_at',
    ];

    protected $casts = [
        'baseline' => 'array',
        'result' => 'array',
        'applied_at' => 'datetime',
        'measured_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->uuid)) {
                $m->uuid = UuidV7::generate();
            }
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SeoEntity::class, 'entity_id');
    }

    public function answerUnit(): BelongsTo
    {
        return $this->belongsTo(SeoAnswerUnit::class, 'answer_unit_id');
    }
}
