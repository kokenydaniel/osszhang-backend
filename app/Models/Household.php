<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $fillable = [
        'name', 'invite_code', 'categories', 'manual_balance',
        'business_enabled', 'business_name', 'shopify_shop_url', 'shopify_access_token', 'utility_split_enabled',
        'utility_split_partner_id'
    ];

    protected $casts = [
        'categories' => 'array',
        'manual_balance' => 'float',
        'business_enabled' => 'boolean',
        'utility_split_enabled' => 'boolean',
        'utility_split_partner_id' => 'integer'
    ];

    public function users() { return $this->hasMany(User::class); }
    public function utilitySplitPartner() { return $this->belongsTo(User::class, 'utility_split_partner_id'); }
    public function transactions() { return $this->hasMany(Transaction::class); }
    public function utilities() { return $this->hasMany(Utility::class); }
    public function meters() { return $this->hasMany(Meter::class); }
    public function businessOrders() { return $this->hasMany(BusinessOrder::class); }
    public function debts() { return $this->hasMany(Debt::class); }
    public function savings() { return $this->hasMany(Saving::class); }
}
