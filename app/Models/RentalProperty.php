<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class RentalProperty extends Model
{
    protected $fillable = [
        'household_id',
        'name',
        'address',
        'monthly_rent',
        'monthly_common_cost',
        'currency',
        'tenant_name',
        'due_day',
        'notes',
        'agreement_notes',
        'contract_ends_at',
        'is_active',
        'budget_sync_enabled',
    ];

    protected $casts = [
        'monthly_rent' => 'float',
        'monthly_common_cost' => 'float',
        'contract_ends_at' => 'date',
        'is_active' => 'boolean',
        'budget_sync_enabled' => 'boolean',
    ];

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function incomeEntries(): HasMany
    {
        return $this->hasMany(RentalIncomeEntry::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(RentalExpense::class);
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
