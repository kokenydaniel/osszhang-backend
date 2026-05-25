<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Saving extends Model
{
    public const TYPE_ACCOUNT = 'account';

    public const TYPE_GOAL = 'goal';

    protected $fillable = [
        'household_id',
        'wallet_id',
        'type',
        'institution',
        'currency',
        'owner',
        'count_in_savings',
        'goal_amount',
        'current_amount',
        'target_date',
        'encrypted_payload',
    ];

    protected $casts = [
        'count_in_savings' => 'boolean',
        'goal_amount' => 'float',
        'current_amount' => 'float',
        'target_date' => 'date',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'saving_id');
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
