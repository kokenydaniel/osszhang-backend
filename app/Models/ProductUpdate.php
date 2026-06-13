<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductUpdate extends Model
{
    protected $fillable = [
        'title',
        'subtitle',
        'body',
        'bullets',
        'location_hint',
        'kind',
        'module_id',
        'required_tier',
        'audience_role',
        'cta_label',
        'cta_href',
        'hero_icon',
        'is_active',
        'published_at',
        'expires_at',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'bullets' => 'array',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
            'expires_at' => 'datetime',
            'priority' => 'integer',
        ];
    }

    /** @return HasMany<ProductUpdateDismissal, $this> */
    public function dismissals(): HasMany
    {
        return $this->hasMany(ProductUpdateDismissal::class);
    }
}
