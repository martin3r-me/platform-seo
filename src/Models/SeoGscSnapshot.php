<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GSC-Snapshot je URL je Tag — der Sichtbarkeits-Verlauf (echte Google-Zahlen).
 * Geschrieben vom GscCollector, wenn frische Search-Analytics kommen.
 */
class SeoGscSnapshot extends Model
{
    protected $table = 'seo_gsc_snapshots';

    protected $fillable = [
        'url_id',
        'snapshot_date',
        'clicks_28d',
        'impressions_28d',
        'ctr',
        'avg_position',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'clicks_28d' => 'integer',
        'impressions_28d' => 'integer',
        'ctr' => 'decimal:4',
        'avg_position' => 'decimal:2',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }
}
