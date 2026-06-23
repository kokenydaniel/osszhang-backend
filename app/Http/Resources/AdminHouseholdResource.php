<?php

namespace App\Http\Resources;

use App\Models\Household;
use App\Support\HouseholdTierAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminHouseholdResource extends JsonResource
{

    public function __construct($resource, private readonly bool $detailed = false, private readonly ?array $aiUsage = null)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $billingTier = HouseholdTierAccess::billingTier($this->resource);
        $accessTier = HouseholdTierAccess::accessTier($this->resource);
        $activeGrant = HouseholdTierAccess::activeGrantTier($this->resource);

        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'business_name' => filled($this->business_name) ? $this->business_name : null,
            'businessName' => filled($this->business_name) ? $this->business_name : null,
            'billing_tier' => $billingTier,
            'billingTier' => $billingTier,
            'access_tier' => $accessTier,
            'accessTier' => $accessTier,
            'subscription_tier' => $billingTier,
            'subscriptionTier' => $billingTier,
            'subscription_status' => $this->subscription_status ?? 'none',
            'subscriptionStatus' => $this->subscription_status ?? 'none',
            'tier_grant' => $this->tier_grant,
            'tierGrant' => $this->tier_grant,
            'tier_grant_expires_at' => $this->tier_grant_expires_at?->toIso8601String(),
            'tierGrantExpiresAt' => $this->tier_grant_expires_at?->toIso8601String(),
            'tier_grant_is_permanent' => $this->tier_grant !== null && $this->tier_grant_expires_at === null,
            'tierGrantIsPermanent' => $this->tier_grant !== null && $this->tier_grant_expires_at === null,
            'tier_grant_note' => $this->tier_grant_note,
            'tierGrantNote' => $this->tier_grant_note,
            'tier_grant_active' => $activeGrant !== null,
            'tierGrantActive' => $activeGrant !== null,
            'members_count' => $this->users_count ?? $this->users()->count(),
            'membersCount' => $this->users_count ?? $this->users()->count(),
            'active_members_count' => $this->active_users_count ?? null,
            'activeMembersCount' => $this->active_users_count ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $payload;
        }

        return array_merge($payload, [
            'onboarding_completed' => (bool) $this->onboarding_completed,
            'onboardingCompleted' => (bool) $this->onboarding_completed,
            'categories' => $this->categories ?? [],
            'categories_count' => is_array($this->categories) ? count($this->categories) : 0,
            'categoriesCount' => is_array($this->categories) ? count($this->categories) : 0,
            'modules' => [
                'budget' => (bool) $this->budget_enabled,
                'savings' => (bool) $this->savings_enabled,
                'debts' => (bool) $this->debts_enabled,
                'utilities' => (bool) $this->utilities_enabled,
                'meters' => (bool) $this->meters_enabled,
                'business' => (bool) $this->business_enabled,
                'pocket_money' => (bool) $this->pocket_money_enabled,
                'insurance' => (bool) $this->insurance_enabled,
                'rental' => (bool) $this->rental_enabled,
                'receivables' => (bool) $this->receivables_enabled,
                'travel_planner' => (bool) $this->travel_planner_enabled,
                'utility_split' => (bool) $this->utility_split_enabled,
            ],
            'integrations' => [
                'shopify' => [
                    'enabled' => (bool) $this->shopify_import_enabled,
                    'configured' => (bool) $this->has_shopify_token,
                    'shop_url' => $this->shopify_shop_url,
                ],
                'woocommerce' => [
                    'enabled' => (bool) $this->woocommerce_import_enabled,
                    'configured' => (bool) $this->has_woocommerce_credentials,
                    'shop_url' => $this->woocommerce_shop_url,
                ],
                'unas' => [
                    'enabled' => (bool) $this->unas_import_enabled,
                    'configured' => (bool) $this->has_unas_api_key,
                    'shop_id' => $this->unas_shop_id,
                ],
                'sumup' => [
                    'enabled' => (bool) $this->sumup_import_enabled,
                    'configured' => (bool) $this->has_sumup_api_key,
                    'merchant_code' => $this->sumup_merchant_code,
                ],
            ],
            'stats' => [
                'wallets' => (int) ($this->wallets_count ?? 0),
                'transactions' => (int) ($this->transactions_count ?? 0),
                'debts' => (int) ($this->debts_count ?? 0),
                'savings' => (int) ($this->savings_count ?? 0),
                'utilities' => (int) ($this->utilities_count ?? 0),
                'meters' => (int) ($this->meters_count ?? 0),
                'business_orders' => (int) ($this->business_orders_count ?? 0),
            ],
            'ai_usage' => $this->aiUsage ?? [
                'total_prompt_tokens' => 0,
                'total_completion_tokens' => 0,
                'total_tokens' => 0,
                'request_count' => 0,
                'cost_usd' => 0,
                'requests_without_cost' => 0,
                'month_prompt_tokens' => 0,
                'month_completion_tokens' => 0,
                'month_total_tokens' => 0,
                'month_request_count' => 0,
                'month_cost_usd' => 0,
                'month_requests_without_cost' => 0,
                'by_feature' => [],
                'last_used_at' => null,
                'pricing_configured' => false,
                'pricing' => [
                    'source_url' => 'https://platform.openai.com/docs/pricing',
                    'last_verified' => null,
                    'tier' => 'standard',
                    'default_model' => (string) config('openai.model', 'gpt-4o-mini'),
                    'default_model_rates' => null,
                ],
            ],
            'ai_settings' => [
                'usage_blocked' => (bool) $this->ai_usage_blocked,
                'usageBlocked' => (bool) $this->ai_usage_blocked,
                'monthly_token_limit' => $this->ai_monthly_token_limit,
                'monthlyTokenLimit' => $this->ai_monthly_token_limit,
                'monthly_limit_reached' => $this->monthlyAiLimitReached(),
                'monthlyLimitReached' => $this->monthlyAiLimitReached(),
            ],
            'members' => $this->relationLoaded('users')
                ? AdminHouseholdMemberResource::collection($this->users)->resolve()
                : [],
        ]);
    }

    private function monthlyAiLimitReached(): bool
    {
        $limit = $this->ai_monthly_token_limit;
        if ($limit === null || $limit <= 0 || $this->aiUsage === null) {
            return false;
        }

        return (int) ($this->aiUsage['month_total_tokens'] ?? 0) >= $limit;
    }
}
