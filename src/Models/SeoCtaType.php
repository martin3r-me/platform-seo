<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein kuratierter CTA-Typ (Mechanik/Absicht) eines Teams — anruf, kontakt,
 * angebot … Referenzdaten, per MCP pflegbar. Ein SeoCta zeigt auf genau einen
 * Typ. `mechanism` (tel/form/link/email) ist die implizite Umsetzung.
 */
class SeoCtaType extends Model
{
    protected $table = 'seo_cta_types';

    public const MECHANISMS = ['tel', 'form', 'link', 'email'];

    protected $fillable = [
        'team_id',
        'code',
        'label',
        'mechanism',
        'sort',
        'active',
    ];

    protected $casts = [
        'team_id' => 'integer',
        'sort' => 'integer',
        'active' => 'boolean',
    ];
}
