<?php

namespace App\Support;

use App\Models\User;

final class AccessControl
{
    public const TIER_FREE = 'free';

    public const TIER_PRO = 'pro';

    public const TIER_PREMIUM = 'premium';

    public const STATUS_NONE = 'none';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_TRIALING = 'trialing';

    /** @var list<string> */
    public const MODULES = ['budget', 'savings', 'debts', 'utilities', 'meters', 'business'];

    /** @var list<string> */
    public const PRO_MODULES = ['savings', 'debts', 'utilities', 'meters'];

    /** @var list<string> */
    public const PREMIUM_MODULES = ['business'];

    /** @var list<string> */
    public const PREMIUM_FEATURES = ['shopify_import', 'ai'];

    public static function isBetaMode(): bool
    {
        return PlatformSettings::isBetaMode();
    }

    public static function effectiveTier(User $user): string
    {
        if ($user->lifetime_admin) {
            return self::TIER_PREMIUM;
        }

        return $user->household?->subscription_tier ?? self::TIER_FREE;
    }

    public static function canAccessModule(User $user, string $moduleId): bool
    {
        if ($user->lifetime_admin || self::isBetaMode()) {
            return true;
        }

        $tier = self::effectiveTier($user);

        if ($moduleId === 'budget') {
            return true;
        }

        if (in_array($moduleId, self::PRO_MODULES, true)) {
            return in_array($tier, [self::TIER_PRO, self::TIER_PREMIUM], true);
        }

        if (in_array($moduleId, self::PREMIUM_MODULES, true)) {
            return $tier === self::TIER_PREMIUM;
        }

        return false;
    }

    public static function canUseFeature(User $user, string $featureId): bool
    {
        if ($user->lifetime_admin || self::isBetaMode()) {
            return true;
        }

        if (! in_array($featureId, self::PREMIUM_FEATURES, true)) {
            return false;
        }

        return self::effectiveTier($user) === self::TIER_PREMIUM;
    }

    public static function canCreatePrivateWallet(User $user): bool
    {
        if ($user->lifetime_admin || self::isBetaMode()) {
            return true;
        }

        return in_array(self::effectiveTier($user), [self::TIER_PRO, self::TIER_PREMIUM], true);
    }

    public static function maxWallets(User $user): ?int
    {
        if ($user->lifetime_admin || self::isBetaMode() || self::effectiveTier($user) !== self::TIER_FREE) {
            return null;
        }

        return 1;
    }

    public static function canAccessWallet(User $user, int $householdId, bool $isShared, ?int $ownerId): bool
    {
        if ($user->household_id !== $householdId) {
            return false;
        }

        return $isShared || $ownerId === $user->id;
    }
}
