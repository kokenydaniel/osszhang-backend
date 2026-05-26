<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\AccessControl;
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
            'household_subscription_tier' => $household?->subscription_tier ?? AccessControl::TIER_FREE,
            'householdSubscriptionTier' => $household?->subscription_tier ?? AccessControl::TIER_FREE,
            'effective_tier' => AccessControl::resolvedAccessTier($this->resource),
            'effectiveTier' => AccessControl::resolvedAccessTier($this->resource),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
