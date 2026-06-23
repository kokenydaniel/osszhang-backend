<?php

namespace App\Support;

use App\Models\Household;

final class HouseholdTierAccess
{
    public static function tierRank(string $tier): int
    {
        return match ($tier) {
            AccessControl::TIER_PREMIUM => 2,
            AccessControl::TIER_PRO => 1,
            default => 0,
        };
    }

    public static function billingTier(?Household $household): string
    {
        if ($household === null) {
            return AccessControl::TIER_FREE;
        }

        $tier = $household->subscription_tier ?? AccessControl::TIER_FREE;

        return in_array($tier, [AccessControl::TIER_PRO, AccessControl::TIER_PREMIUM], true)
            ? $tier
            : AccessControl::TIER_FREE;
    }

    public static function activeGrantTier(?Household $household): ?string
    {
        if ($household === null || $household->tier_grant === null) {
            return null;
        }

        if ($household->tier_grant_expires_at !== null && $household->tier_grant_expires_at->isPast()) {
            return null;
        }

        $grant = $household->tier_grant;

        return in_array($grant, [AccessControl::TIER_PRO, AccessControl::TIER_PREMIUM], true)
            ? $grant
            : null;
    }

    public static function accessTier(?Household $household): string
    {
        $billing = self::billingTier($household);
        $grant = self::activeGrantTier($household);

        if ($grant === null) {
            return $billing;
        }

        return self::tierRank($grant) > self::tierRank($billing) ? $grant : $billing;
    }

    public static function grantPayload(?Household $household): ?array
    {
        $grant = self::activeGrantTier($household);
        if ($grant === null || $household === null) {
            return null;
        }

        return [
            'tier' => $grant,
            'expires_at' => $household->tier_grant_expires_at?->toIso8601String(),
            'expiresAt' => $household->tier_grant_expires_at?->toIso8601String(),
            'is_permanent' => $household->tier_grant_expires_at === null,
            'isPermanent' => $household->tier_grant_expires_at === null,
            'note' => $household->tier_grant_note,
        ];
    }
}
