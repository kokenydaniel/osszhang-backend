<?php

namespace App\Http\Resources;

use App\Services\EncryptedRecordService;
use App\Services\WalletProvisioningService;
use App\Support\HouseholdTierAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HouseholdResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $crypto = app(EncryptedRecordService::class);
        $sensitive = $crypto->householdSensitive($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'invite_code' => $this->invite_code,
            'categories' => $this->categories,
            'budget_settings' => $this->resolvedBudgetSettings(),
            'manual_balance' => (float) (app(WalletProvisioningService::class)
                ->sharedWalletForHousehold($this->resource)
                ->manual_balance ?? 0),
            'budget_enabled' => $this->budget_enabled,
            'savings_enabled' => $this->savings_enabled,
            'debts_enabled' => $this->debts_enabled,
            'utilities_enabled' => $this->utilities_enabled,
            'meters_enabled' => $this->meters_enabled,
            'savings_settings' => $this->resolvedSavingsSettings(),
            'debts_settings' => $this->resolvedDebtsSettings(),
            'meters_settings' => $this->resolvedMetersSettings(),
            'utilities_settings' => $this->resolvedUtilitiesSettings(),
            'dashboard_settings' => $this->resolvedDashboardSettings(),
            'onboarding_completed' => (bool) $this->onboarding_completed,
            'subscription_tier' => HouseholdTierAccess::billingTier($this->resource),
            'subscription_status' => $this->subscription_status ?? 'none',
            'subscriptionTier' => HouseholdTierAccess::billingTier($this->resource),
            'subscriptionStatus' => $this->subscription_status ?? 'none',
            'billing_tier' => HouseholdTierAccess::billingTier($this->resource),
            'billingTier' => HouseholdTierAccess::billingTier($this->resource),
            'access_tier' => HouseholdTierAccess::accessTier($this->resource),
            'accessTier' => HouseholdTierAccess::accessTier($this->resource),
            'tier_grant' => HouseholdTierAccess::grantPayload($this->resource),
            'tierGrant' => HouseholdTierAccess::grantPayload($this->resource),
            'business_enabled' => $this->business_enabled,
            'business_name' => $this->business_name,
            'shopify_import_enabled' => (bool) $this->shopify_import_enabled,
            'woocommerce_import_enabled' => (bool) $this->woocommerce_import_enabled,
            'unas_import_enabled' => (bool) $this->unas_import_enabled,
            'pocket_money_enabled' => (bool) $this->pocket_money_enabled,
            'pocket_money_settings' => $this->resolvedPocketMoneySettings(),
            'insurance_enabled' => (bool) $this->insurance_enabled,
            'insurance_settings' => $this->resolvedInsuranceSettings(),
            'rental_enabled' => (bool) $this->rental_enabled,
            'rental_settings' => $this->resolvedRentalSettings(),
            'business_settings' => $this->resolvedBusinessSettings(),
            'shopify_shop_url' => $this->shopify_shop_url,
            'woocommerce_shop_url' => $this->woocommerce_shop_url,
            'unas_shop_id' => $this->unas_shop_id,
            'has_shopify_token' => $this->has_shopify_token,
            'has_woocommerce_credentials' => $this->has_woocommerce_credentials,
            'has_unas_api_key' => $this->has_unas_api_key,
            'sumup_import_enabled' => (bool) $this->sumup_import_enabled,
            'sumup_merchant_code' => $this->sumup_merchant_code,
            'has_sumup_api_key' => $this->has_sumup_api_key,
            'utility_split_enabled' => $this->utility_split_enabled,
            'utility_split_partner_id' => $this->utility_split_partner_id,
            'utility_templates' => $sensitive['utility_templates'] ?? [],
            'users' => $this->whenLoaded(
                'users',
                fn () => UserResource::collection($this->users)->resolve(),
            ),
        ];
    }
}
