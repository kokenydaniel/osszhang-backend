<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Investment extends Model
{
    protected $fillable = [
        'household_id', 'name', 'type', 'principal_amount', 
        'annual_interest_rate', 'purchase_date', 'maturity_date', 
        'owner', 'count_in_savings', 'current_value',
        'maturity_amount', 'next_payout_amount', 'next_payout_date',
        'encrypted_payload',
    ];

    protected $casts = [
        'count_in_savings' => 'boolean',
        'purchase_date' => 'date',
        'maturity_date' => 'date',
        'next_payout_date' => 'date',
    ];
}
