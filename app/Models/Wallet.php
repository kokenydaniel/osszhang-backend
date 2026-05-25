<?php

namespace App\Models;

use App\Support\AccessControl;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Wallet extends Model
{
    public const SHARED_WALLET_NAME = 'Közös kassza';

    protected $fillable = [
        'household_id',
        'name',
        'owner_id',
        'is_shared',
        'manual_balance',
    ];

    protected $casts = [
        'is_shared' => 'boolean',
        'manual_balance' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(function (Wallet $wallet) {
            if ($wallet->is_shared && $wallet->owner_id !== null) {
                throw ValidationException::withMessages([
                    'owner_id' => 'A közös kasszának nincs tulajdonosa.',
                ]);
            }

            if (! $wallet->is_shared && $wallet->owner_id === null) {
                throw ValidationException::withMessages([
                    'owner_id' => 'A privát kasszának kötelező a tulajdonos.',
                ]);
            }
        });
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function savings(): HasMany
    {
        return $this->hasMany(Saving::class);
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function scopeForHousehold(Builder $query, int $householdId): Builder
    {
        return $query->where('household_id', $householdId);
    }

    public function scopeAccessibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->where('household_id', $user->household_id)
            ->where(function (Builder $inner) use ($user) {
                $inner->where('is_shared', true)
                    ->orWhere('owner_id', $user->id);
            });
    }

    public function isAccessibleTo(User $user): bool
    {
        return AccessControl::canAccessWallet(
            $user,
            $this->household_id,
            $this->is_shared,
            $this->owner_id,
        );
    }
}
