<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Conversion-Snapshot je URL je Tag — der Wirkungs-Verlauf. Geschrieben vom
 * PlausibleCollector, wenn frische Goal-Daten kommen.
 */
class SeoConversionSnapshot extends Model
{
    protected $table = 'seo_conversion_snapshots';

    protected $fillable = [
        'url_id',
        'snapshot_date',
        'conversions_30d',
        'conversion_rate',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'conversions_30d' => 'integer',
        'conversion_rate' => 'decimal:2',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }
}
