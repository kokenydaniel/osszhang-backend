<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalIncomeEntry extends Model
{
    protected $fillable = [
        'rental_property_id',
        'amount',
        'currency',
        'period_year',
        'period_month',
        'due_date',
        'rent_amount',
        'common_cost_amount',
        'paid_date',
        'note',
    ];

    protected $casts = [
        'amount' => 'float',
        'rent_amount' => 'float',
        'common_cost_amount' => 'float',
        'due_date' => 'date',
        'paid_date' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(RentalProperty::class, 'rental_property_id');
    }
}
