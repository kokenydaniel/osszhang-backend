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
            'household_name' => $household?->business_name,
            'householdName' => $household?->business_name,
            'effective_tier' => AccessControl::effectiveTier($this->resource),
            'effectiveTier' => AccessControl::effectiveTier($this->resource),
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
