<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    protected $fillable = [
        'household_id', 'wallet_id', 'user_id', 'type', 'description', 'category', 'amount', 'currency',
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

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where('household_id', $user->household_id)
            ->where(function (Builder $inner) use ($user) {
                $inner->whereNull('wallet_id')
                    ->orWhereHas('wallet', fn (Builder $walletQuery) => $walletQuery->accessibleTo($user));
            });
    }

    public function scopeForWallet(Builder $query, Wallet $wallet): Builder
    {
        return $query->where('wallet_id', $wallet->id);
    }
}
