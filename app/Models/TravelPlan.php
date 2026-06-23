<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TravelPlan extends Model
{
    protected $fillable = [
        'household_id',
        'user_id',
        'wallet_id',
        'saving_id',
        'destination',
        'origin_location',
        'duration_days',
        'travelers_count',
        'total_budget',
        'target_date',
        'trip_style',
        'accommodation_preference',
        'transport_mode',
        'transport_already_booked',
        'accommodation_already_booked',
        'car_fuel_consumption_l100',
        'plan_payload',
        'input_payload',
        'financial_context',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'travelers_count' => 'integer',
        'total_budget' => 'float',
        'target_date' => 'date',
        'transport_already_booked' => 'boolean',
        'accommodation_already_booked' => 'boolean',
        'car_fuel_consumption_l100' => 'float',
        'plan_payload' => 'array',
        'input_payload' => 'array',
        'financial_context' => 'array',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function linkedSaving(): BelongsTo
    {
        return $this->belongsTo(Saving::class, 'saving_id');
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
}
