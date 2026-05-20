<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'household_id', 'user_id', 'type', 'description', 'category', 'amount',
        'due_date', 'paid_date', 'is_budget', 'is_reserve', 'encrypted_payload',
    ];

    protected $casts = [
        'is_budget' => 'boolean',
        'is_reserve' => 'boolean',
        'amount' => 'float',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }
}
