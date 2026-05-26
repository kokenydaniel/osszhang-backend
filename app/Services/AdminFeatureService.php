<?php

namespace App\Services;

use App\Http\Resources\FeatureFlagResource;
use App\Models\FeatureFlag;
use Illuminate\Database\Eloquent\Collection;

class AdminFeatureService
{
    /** @return Collection<int, FeatureFlag> */
    public function listFlags(): Collection
    {
        return FeatureFlag::query()->orderBy('key')->get();
    }

    public function updateFlag(string $key, bool $value): array
    {
        $flag = FeatureFlag::query()->where('key', $key)->firstOrFail();
        $flag->update(['value' => $value]);

        \App\Support\FeatureFlags::clearCache();

        return (new FeatureFlagResource($flag->fresh()))->resolve();
    }
}
