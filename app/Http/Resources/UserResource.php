<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'role' => $this->role,
            'permissions' => $this->permissions ?? [],
            'must_change_password' => (bool) $this->must_change_password,
            'lifetime_admin' => (bool) $this->lifetime_admin,
        ];
    }
}
