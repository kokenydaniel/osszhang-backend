<?php

namespace App\Models;

use App\Casts\LenientEncrypted;
use App\Support\BusinessSettings as BusinessSettingsSupport;
use App\Support\DebtsSettings as DebtsSettingsSupport;
use App\Support\MetersSettings as MetersSettingsSupport;
use App\Support\SavingsSettings as SavingsSettingsSupport;
use App\Support\UtilityTemplates as UtilityTemplatesSupport;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Services\WalletProvisioningService;
use Illuminate\Database\Eloquent\Model;

class Household extends Model
{
    protected $fillable = [
        'name', 'invite_code', 'categories', 'manual_balance',
        'budget_enabled', 'savings_enabled', 'debts_enabled', 'utilities_enabled', 'meters_enabled',
        'savings_settings', 'debts_settings', 'meters_settings', 'onboarding_completed',
        'business_enabled', 'business_name', 'shopify_import_enabled', 'shopify_shop_url', 'shopify_access_token', 'utility_split_enabled',
        'utility_split_partner_id', 'utility_templates', 'cipher_key_encrypted', 'sensitive_encrypted',
        'subscription_tier', 'subscription_status',
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
        'budget_enabled' => 'boolean',
        'savings_enabled' => 'boolean',
        'debts_enabled' => 'boolean',
        'utilities_enabled' => 'boolean',
        'meters_enabled' => 'boolean',
        'savings_settings' => 'array',
        'debts_settings' => 'array',
        'meters_settings' => 'array',
        'onboarding_completed' => 'boolean',
        'subscription_tier' => 'string',
        'subscription_status' => 'string',
        'business_enabled' => 'boolean',
        'shopify_import_enabled' => 'boolean',
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

    public function resolvedSavingsSettings(): array
    {
        return SavingsSettingsSupport::resolve($this->savings_settings);
    }

    public function resolvedDebtsSettings(): array
    {
        return DebtsSettingsSupport::resolve($this->debts_settings);
    }

    public function resolvedMetersSettings(): array
    {
        return MetersSettingsSupport::resolve($this->meters_settings);
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

        static::created(function (Household $household) {
            app(WalletProvisioningService::class)->ensureSharedWallet($household);
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
    public function wallets() { return $this->hasMany(Wallet::class); }
}
