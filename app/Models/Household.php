<?php

namespace App\Models;

use App\Casts\LenientEncrypted;
use App\Support\BusinessSettings as BusinessSettingsSupport;
use App\Support\UtilityTemplates as UtilityTemplatesSupport;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $fillable = [
        'name', 'invite_code', 'categories', 'manual_balance',
        'business_enabled', 'business_name', 'shopify_shop_url', 'shopify_access_token', 'utility_split_enabled',
        'utility_split_partner_id', 'utility_templates', 'cipher_key_encrypted', 'sensitive_encrypted',
    ];

    protected $hidden = [
        'shopify_access_token',
        'cipher_key_encrypted',
    ];

    protected $appends = [
        'has_shopify_token',
    ];

    protected $casts = [
        'categories' => 'array',
        'manual_balance' => 'float',
        'business_enabled' => 'boolean',
        'utility_split_enabled' => 'boolean',
        'shopify_access_token' => LenientEncrypted::class,
        'business_settings' => 'array',
        'utility_templates' => 'array',
    ];

    public function resolvedBusinessSettings(): array
    {
        return BusinessSettingsSupport::resolve($this->business_settings);
    }

    public function resolvedUtilityTemplates(): array
    {
        return UtilityTemplatesSupport::resolve($this->utility_templates);
    }

    protected static function booted(): void
    {
        static::creating(function (Household $household) {
            if (empty($household->cipher_key_encrypted)) {
                $household->cipher_key_encrypted = \Illuminate\Support\Facades\Crypt::encryptString(
                    base64_encode(random_bytes(32))
                );
            }
            if (empty($household->business_settings)) {
                $household->business_settings = BusinessSettingsSupport::defaults();
            }
        });
    }

    protected function hasShopifyToken(): Attribute
    {
        return Attribute::get(fn () => filled($this->shopify_access_token));
    }

    public function users() { return $this->hasMany(User::class); }
    public function utilitySplitPartner() { return $this->belongsTo(User::class, 'utility_split_partner_id'); }
    public function transactions() { return $this->hasMany(Transaction::class); }
    public function utilities() { return $this->hasMany(Utility::class); }
    public function utilitySettlements() { return $this->hasMany(UtilitySettlement::class); }
    public function meters() { return $this->hasMany(Meter::class); }
    public function businessOrders() { return $this->hasMany(BusinessOrder::class); }
    public function debts() { return $this->hasMany(Debt::class); }
    public function savings() { return $this->hasMany(Saving::class); }
}
