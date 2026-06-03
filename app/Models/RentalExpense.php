<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalExpense extends Model
{
    protected $fillable = [
        'rental_property_id',
        'expense_type',
        'amount',
        'currency',
        'expense_date',
        'note',
    ];

    protected $casts = [
        'amount' => 'float',
        'expense_date' => 'date',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(RentalProperty::class, 'rental_property_id');
    }
}
