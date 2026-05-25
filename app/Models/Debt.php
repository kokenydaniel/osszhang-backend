<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Debt extends Model
{
    protected $fillable = [
        'household_id',
        'wallet_id',
        'name',
        'target_amount',
        'paid_amount',
        'annual_interest_rate',
        'minimum_payment',
        'due_day',
        'status',
        'encrypted_payload',
    ];

    protected $casts = [
        'target_amount' => 'float',
        'paid_amount' => 'float',
        'annual_interest_rate' => 'float',
        'minimum_payment' => 'float',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where('household_id', $user->household_id)
            ->whereHas('wallet', fn (Builder $walletQuery) => $walletQuery->accessibleTo($user));
    }

    public function scopeForWallet(Builder $query, Wallet $wallet): Builder
    {
        return $query->where('wallet_id', $wallet->id);
    }
}
