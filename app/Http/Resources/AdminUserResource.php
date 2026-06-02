<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\AccessControl;
use App\Support\HouseholdTierAccess;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $household = $this->household;
        $businessName = filled($household?->business_name) ? $household->business_name : null;

        return [
            'id' => $this->id,
            'username' => $this->username,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'role' => $this->role,
            'lifetime_admin' => (bool) $this->lifetime_admin,
            'lifetimeAdmin' => (bool) $this->lifetime_admin,
            'is_active' => (bool) $this->is_active,
            'isActive' => (bool) $this->is_active,
            'household_id' => $this->household_id,
            'householdId' => $this->household_id,
            'household_name' => $household?->name,
            'householdName' => $household?->name,
            'business_name' => $businessName,
            'businessName' => $businessName,
            'household_subscription_tier' => HouseholdTierAccess::billingTier($household),
            'householdSubscriptionTier' => HouseholdTierAccess::billingTier($household),
            'billing_tier' => HouseholdTierAccess::billingTier($household),
            'billingTier' => HouseholdTierAccess::billingTier($household),
            'effective_tier' => AccessControl::resolvedAccessTier($this->resource),
            'effectiveTier' => AccessControl::resolvedAccessTier($this->resource),
            'tier_grant' => $household?->tier_grant,
            'tierGrant' => $household?->tier_grant,
            'tier_grant_expires_at' => $household?->tier_grant_expires_at?->toIso8601String(),
            'tierGrantExpiresAt' => $household?->tier_grant_expires_at?->toIso8601String(),
            'tier_grant_is_permanent' => $household?->tier_grant !== null && $household->tier_grant_expires_at === null,
            'tierGrantIsPermanent' => $household?->tier_grant !== null && $household->tier_grant_expires_at === null,
            'tier_grant_note' => $household?->tier_grant_note,
            'tierGrantNote' => $household?->tier_grant_note,
            'tier_grant_active' => HouseholdTierAccess::activeGrantTier($household) !== null,
            'tierGrantActive' => HouseholdTierAccess::activeGrantTier($household) !== null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
