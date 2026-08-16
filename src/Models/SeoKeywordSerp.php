<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistierter SERP-Stand eines Keywords (Top-10-URLs) — Cache fürs
 * Nach-Clustern. Macht den Abruf wiederaufsetzbar: ein bereits geholtes
 * Keyword wird beim Fortsetzen übersprungen (kein erneuter Live-Call, kein
 * doppeltes Geld). Siehe SeoClusteringService.
 */
class SeoKeywordSerp extends Model
{
    protected $fillable = [
        'team_id',
        'keyword_id',
        'urls',
        'fetched_at',
    ];

    protected $casts = [
        'urls' => 'array',
        'fetched_at' => 'datetime',
    ];
}
