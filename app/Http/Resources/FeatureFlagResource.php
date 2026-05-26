<?php

namespace App\Http\Resources;

use App\Models\FeatureFlag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin FeatureFlag */
class FeatureFlagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'value' => (bool) $this->value,
            'description' => $this->description,
        ];
    }
}
