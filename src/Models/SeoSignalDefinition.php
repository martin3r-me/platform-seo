<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

/**
 * Eine Signal-Definition — deklariert, WANN ein SEO-Signal entsteht.
 * DB-Objekt (in UI + per LLM-Tool bearbeitbar), domänennativ. docs/SIGNALS-CONCEPT.md.
 */
class SeoSignalDefinition extends Model
{
    use SoftDeletes;

    protected $table = 'seo_signal_definitions';

    protected $fillable = [
        'uuid',
        'team_id',
        'created_by',
        'name',
        'description',
        'pattern_type',
        'conditions',
        'scope_type',
        'scope_value',
        'frequency',
        'severity',
        'is_active',
    ];

    protected $casts = [
        'uuid' => 'string',
        'conditions' => 'array',
        'scope_value' => 'array',
        'is_active' => 'boolean',
    ];

    public const SCOPE_TYPES = ['all', 'entity', 'entity_subtree', 'list'];

    public const FREQUENCIES = ['every_snapshot', 'daily', 'weekly'];

    public const SEVERITIES = ['info', 'watch', 'warning', 'critical'];

    /**
     * Katalog der domänennativen Muster: Label, Kurzbeschreibung und die tunbaren
     * Bedingungen (mit Default) je Muster. Speist UI-Formular und Tool-Validierung.
     *
     * @return array<string, array{label:string, description:string, conditions:array<string,mixed>}>
     */
    public static function patternCatalog(): array
    {
        return [
            'striking_distance' => [
                'label' => 'Griffweite (Pos. 4–10)',
                'description' => 'URL rankt knapp außerhalb Top-3 für ein nachgefragtes Keyword — der klarste Ausbau-Hebel.',
                'conditions' => ['min_position' => 4, 'max_position' => 10, 'min_volume' => 100],
            ],
            'position_drop' => [
                'label' => 'Position gefallen',
                'description' => 'Ranking über die Snapshots deutlich abgerutscht — Ursache prüfen.',
                'conditions' => ['min_drop' => 3, 'min_volume' => 50],
            ],
            'position_gain' => [
                'label' => 'Position gewonnen',
                'description' => 'Ranking deutlich verbessert — Momentum sichtbar machen/halten.',
                'conditions' => ['min_gain' => 3, 'min_volume' => 50],
            ],
            'cannibalization' => [
                'label' => 'Kannibalisierung',
                'description' => 'Mehrere eigene URLs ranken für dasselbe Keyword — konsolidieren/entflechten.',
                'conditions' => ['min_urls' => 2, 'min_volume' => 0],
            ],
            'thin_content' => [
                'label' => 'Dünner Content',
                'description' => 'Rankende URL mit zu wenig Inhalt — Content-Brief (inhaltlich).',
                'conditions' => ['thin_word_count' => 300, 'max_position' => 20, 'min_volume' => 50],
            ],
            'lost_ranking' => [
                'label' => 'Ranking verloren',
                'description' => 'Keyword war in Top-N, ist jetzt raus — zurückerobern.',
                'conditions' => ['was_within' => 20, 'min_volume' => 50],
            ],
            'cluster_underperformance' => [
                'label' => 'Cluster schwächelt',
                'description' => 'Durchdringung eines Clusters fällt — Cluster bespielen (→ Playbook).',
                'conditions' => ['min_penetration_drop' => 5],
            ],
            'decay' => [
                'label' => 'Schleichender Verfall',
                'description' => 'Besucher (30 Tage) schleichend rückläufig — Refresh/Relaunch.',
                'conditions' => ['min_decline_pct' => 20],
            ],
        ];
    }

    public static function patternTypes(): array
    {
        return array_keys(self::patternCatalog());
    }

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function signals(): HasMany
    {
        return $this->hasMany(SeoSignal::class, 'signal_definition_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
