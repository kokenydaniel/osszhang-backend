<?php

namespace App\Models;

use App\Casts\LenientEncrypted;
use App\Services\WalletProvisioningService;
use App\Support\BudgetSettings as BudgetSettingsSupport;
use App\Support\BusinessSettings as BusinessSettingsSupport;
use App\Support\DashboardSettings as DashboardSettingsSupport;
use App\Support\DebtsSettings as DebtsSettingsSupport;
use App\Support\MetersSettings as MetersSettingsSupport;
use App\Support\SavingsSettings as SavingsSettingsSupport;
use App\Support\UtilitiesSettings as UtilitiesSettingsSupport;
use App\Support\UtilityTemplates as UtilityTemplatesSupport;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Household extends Model
{
    protected $fillable = [
        'name', 'invite_code', 'categories', 'budget_settings', 'manual_balance',
        'budget_enabled', 'savings_enabled', 'debts_enabled', 'utilities_enabled', 'meters_enabled',
        'savings_settings', 'debts_settings', 'meters_settings', 'onboarding_completed',
        'business_enabled', 'business_name', 'business_settings', 'shopify_import_enabled', 'shopify_shop_url', 'shopify_access_token',
        'woocommerce_import_enabled', 'woocommerce_shop_url', 'woocommerce_consumer_key', 'woocommerce_consumer_secret',
        'unas_import_enabled', 'unas_shop_id', 'unas_api_key',
        'sumup_import_enabled', 'sumup_merchant_code', 'sumup_api_key',
        'pocket_money_enabled', 'insurance_enabled', 'rental_enabled', 'receivables_enabled', 'travel_planner_enabled',
        'pocket_money_settings', 'insurance_settings', 'rental_settings',
        'utility_split_enabled',
        'utility_split_partner_id', 'utility_templates', 'utilities_settings', 'dashboard_settings',
        'cipher_key_encrypted', 'sensitive_encrypted',
        'subscription_tier', 'subscription_status',
        'tier_grant', 'tier_grant_expires_at', 'tier_grant_note', 'tier_grant_granted_by',
        'ai_usage_blocked', 'ai_monthly_token_limit',
    ];

    protected $hidden = [
        'shopify_access_token',
        'woocommerce_consumer_key',
        'woocommerce_consumer_secret',
        'unas_api_key',
        'sumup_api_key',
        'cipher_key_encrypted',
    ];

    protected $appends = [
        'has_shopify_token',
        'has_woocommerce_credentials',
        'has_unas_api_key',
        'has_sumup_api_key',
    ];

    protected $casts = [
        'categories' => 'array',
        'budget_settings' => 'array',
        'utilities_settings' => 'array',
        'dashboard_settings' => 'array',
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
        'tier_grant' => 'string',
        'tier_grant_expires_at' => 'datetime',
        'ai_usage_blocked' => 'boolean',
        'ai_monthly_token_limit' => 'integer',
        'business_enabled' => 'boolean',
        'shopify_import_enabled' => 'boolean',
        'woocommerce_import_enabled' => 'boolean',
        'unas_import_enabled' => 'boolean',
        'sumup_import_enabled' => 'boolean',
        'pocket_money_enabled' => 'boolean',
        'insurance_enabled' => 'boolean',
        'rental_enabled' => 'boolean',
        'receivables_enabled' => 'boolean',
        'travel_planner_enabled' => 'boolean',
        'utility_split_enabled' => 'boolean',
        'shopify_access_token' => LenientEncrypted::class,
        'woocommerce_consumer_key' => LenientEncrypted::class,
        'woocommerce_consumer_secret' => LenientEncrypted::class,
        'unas_api_key' => LenientEncrypted::class,
        'sumup_api_key' => LenientEncrypted::class,
        'business_settings' => 'array',
        'pocket_money_settings' => 'array',
        'insurance_settings' => 'array',
        'rental_settings' => 'array',
        'utility_templates' => 'array',
    ];

    public function resolvedBudgetSettings(): array
    {
        return BudgetSettingsSupport::resolve($this->budget_settings);
    }

    public function resolvedBusinessSettings(): array
    {
        return BusinessSettingsSupport::resolve($this->business_settings);
    }

    public function resolvedDashboardSettings(): array
    {
        return DashboardSettingsSupport::resolve($this->dashboard_settings);
    }

    public function resolvedUtilitiesSettings(): array
    {
        return UtilitiesSettingsSupport::resolve($this->utilities_settings);
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

    public function resolvedPocketMoneySettings(): array
    {
        return \App\Support\PocketMoneySettings::resolve($this->pocket_money_settings);
    }

    public function resolvedInsuranceSettings(): array
    {
        return \App\Support\InsuranceSettings::resolve($this->insurance_settings);
    }

    public function resolvedRentalSettings(): array
    {
        return \App\Support\RentalSettings::resolve($this->rental_settings);
    }

    public function resolvedMetersSettings(): array
    {
        return MetersSettingsSupport::resolve($this->meters_settings);
    }

    protected static function booted(): void
    {
        static::creating(function (Household $household) {
            if (empty($household->cipher_key_encrypted)) {
                $household->cipher_key_encrypted = Crypt::encryptString(
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

    protected function hasWoocommerceCredentials(): Attribute
    {
        return Attribute::get(fn () => filled($this->woocommerce_consumer_key) && filled($this->woocommerce_consumer_secret));
    }

    protected function hasUnasApiKey(): Attribute
    {
        return Attribute::get(fn () => filled($this->unas_api_key));
    }

    protected function hasSumupApiKey(): Attribute
    {
        return Attribute::get(fn () => filled($this->sumup_api_key));
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function utilitySplitPartner()
    {
        return $this->belongsTo(User::class, 'utility_split_partner_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function utilities()
    {
        return $this->hasMany(Utility::class);
    }

    public function utilitySettlements()
    {
        return $this->hasMany(UtilitySettlement::class);
    }

    public function meters()
    {
        return $this->hasMany(Meter::class);
    }

    public function businessOrders()
    {
        return $this->hasMany(BusinessOrder::class);
    }

    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    public function savings()
    {
        return $this->hasMany(Saving::class);
    }

    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }
}
