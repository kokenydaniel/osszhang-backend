<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InsurancePolicy extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'household_id', 'name', 'insurer', 'policy_kind', 'annual_premium', 'fund_value',
        'premium_free', 'payment_frequency', 'payment_amount', 'currency',
        'renewal_date', 'covered_until', 'notes', 'is_active',
        'budget_sync_enabled', 'budget_start_year', 'budget_start_month', 'budget_due_day',
        'paid_budget_periods',
    ];

    protected $casts = [
        'annual_premium' => 'float',
        'fund_value' => 'float',
        'premium_free' => 'boolean',
        'payment_amount' => 'float',
        'renewal_date' => 'date',
        'covered_until' => 'date',
        'is_active' => 'boolean',
        'budget_sync_enabled' => 'boolean',
        'budget_start_year' => 'integer',
        'budget_start_month' => 'integer',
        'budget_due_day' => 'integer',
        'paid_budget_periods' => 'array',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
