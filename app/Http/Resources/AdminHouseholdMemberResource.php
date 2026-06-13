<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class AdminHouseholdMemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'lastLoginAt' => $this->last_login_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
