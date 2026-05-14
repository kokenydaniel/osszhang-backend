<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $fillable = ['name', 'invite_code', 'categories'];

    protected $casts = [
        'categories' => 'array'
    ];

    public function users() { return $this->hasMany(User::class); }
    public function transactions() { return $this->hasMany(Transaction::class); }
    public function utilities() { return $this->hasMany(Utility::class); }
    public function meters() { return $this->hasMany(Meter::class); }
    public function businessOrders() { return $this->hasMany(BusinessOrder::class); }
    public function debts() { return $this->hasMany(Debt::class); }
    public function savings() { return $this->hasMany(Saving::class); }
}
