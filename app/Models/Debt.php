<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Debt extends Model {
    protected $fillable = [
        'household_id',
        'name',
        'target_amount',
        'paid_amount',
        'annual_interest_rate',
        'minimum_payment',
        'due_day',
        'status'
    ];
}
