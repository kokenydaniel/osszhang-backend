<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessDocument extends Model
{
    protected $fillable = [
        'household_id',
        'uploaded_by',
        'year',
        'month',
        'document_type',
        'business_order_id',
        'label',
        'source',
        'import_key',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'size_bytes' => 'integer',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function businessOrder(): BelongsTo
    {
        return $this->belongsTo(BusinessOrder::class);
    }
}
