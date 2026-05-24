<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Symfony\Component\Uid\UuidV7;

class SeoUrlList extends Model
{
    protected $table = 'seo_url_lists';

    protected $fillable = [
        'uuid',
        'name',
        'slug',
        'description',
        'created_by',
    ];

    protected $casts = [
        'uuid' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function urls(): BelongsToMany
    {
        return $this->belongsToMany(SeoUrl::class, 'seo_url_list_entries', 'list_id', 'url_id')
            ->withPivot('added_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
