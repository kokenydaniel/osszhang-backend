<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductUpdateDismissal extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'product_update_id',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'dismissed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function productUpdate(): BelongsTo
    {
        return $this->belongsTo(ProductUpdate::class);
    }
}
