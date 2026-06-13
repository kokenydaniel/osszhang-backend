<?php

namespace App\Support;

use App\Http\Resources\ProductUpdateResource;
use App\Models\ProductUpdate;
use App\Models\ProductUpdateDismissal;
use App\Models\User;

class ProductUpdates
{
    public static function pendingForUser(User $user): ?array
    {
        $candidates = ProductUpdate::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereDoesntHave('dismissals', function ($query) use ($user): void {
                $query->where('user_id', $user->id);
            })
            ->orderByDesc('priority')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();

        foreach ($candidates as $update) {
            if (self::matchesAudience($user, $update)) {
                return (new ProductUpdateResource($update))->resolve();
            }
        }

        return null;
    }

    public static function dismiss(User $user, ProductUpdate $update): void
    {
        ProductUpdateDismissal::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'product_update_id' => $update->id,
            ],
            [
                'dismissed_at' => now(),
            ],
        );
    }

    public static function matchesAudience(User $user, ProductUpdate $update): bool
    {
        if ($update->audience_role && $update->audience_role !== 'all') {
            if ($user->role !== $update->audience_role) {
                return false;
            }
        }

        if ($update->required_tier && $update->required_tier !== 'all') {
            if (! self::userMeetsTier($user, $update->required_tier)) {
                return false;
            }
        }

        return true;
    }

    public static function userMeetsTier(User $user, string $requiredTier): bool
    {
        return self::tierRank(AccessControl::effectiveTier($user)) >= self::tierRank($requiredTier);
    }

    private static function tierRank(string $tier): int
    {
        return match ($tier) {
            AccessControl::TIER_PREMIUM => 3,
            AccessControl::TIER_PRO => 2,
            default => 1,
        };
    }
}
