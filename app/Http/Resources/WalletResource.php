<?php

namespace App\Http\Resources;

use App\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Wallet */
class WalletResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'household_id' => $this->household_id,
            'householdId' => $this->household_id,
            'name' => $this->name,
            'owner_id' => $this->owner_id,
            'ownerId' => $this->owner_id,
            'is_shared' => $this->is_shared,
            'isShared' => $this->is_shared,
            'manual_balance' => (float) ($this->manual_balance ?? 0),
            'manualBalance' => (float) ($this->manual_balance ?? 0),
        ];
    }
}
