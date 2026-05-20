<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UtilitySettlement extends Model
{
    protected $fillable = [
        'household_id',
        'year',
        'month',
        'amount',
        'direction',
        'settled_at',
        'transaction_id',
        'encrypted_payload',
    ];

    protected $casts = [
        'amount' => 'float',
        'year' => 'integer',
        'month' => 'integer',
        'settled_at' => 'date',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
