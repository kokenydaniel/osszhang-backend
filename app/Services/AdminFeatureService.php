<?php

namespace App\Services;

use App\Http\Resources\FeatureFlagResource;
use App\Models\FeatureFlag;
use App\Support\FeatureFlags;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class AdminFeatureService
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    /** @return Collection<int, FeatureFlag> */
    public function listFlags(): Collection
    {
        return FeatureFlag::query()->orderBy('key')->get();
    }

    public function updateFlag(string $key, bool $value, ?Request $request = null): array
    {
        $flag = FeatureFlag::query()->where('key', $key)->firstOrFail();
        $previous = (bool) $flag->value;
        $flag->update(['value' => $value]);

        FeatureFlags::clearCache();

        if ($request?->user()) {
            $this->auditLogService->record(
                'feature_flag.updated',
                $request->user()->id,
                null,
                FeatureFlag::class,
                $flag->id,
                ['key' => $key, 'from' => $previous, 'to' => $value],
                $request,
            );
        }

        return (new FeatureFlagResource($flag->fresh()))->resolve();
    }
}
