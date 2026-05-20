<?php

namespace App\Http\Resources;

use App\Services\EncryptedRecordService;
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
            'manual_balance' => (float) ($sensitive['manual_balance'] ?? 0),
            'business_enabled' => $this->business_enabled,
            'business_name' => $this->business_name,
            'business_settings' => $this->resolvedBusinessSettings(),
            'shopify_shop_url' => $this->shopify_shop_url,
            'has_shopify_token' => $this->has_shopify_token,
            'utility_split_enabled' => $this->utility_split_enabled,
            'utility_split_partner_id' => $this->utility_split_partner_id,
            'utility_templates' => $sensitive['utility_templates'] ?? [],
            'users' => $this->whenLoaded('users', fn () => $this->users),
        ];
    }
}
