<?php

namespace App\Support;

use App\Models\Household;
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

    public const MODULES = ['budget', 'savings', 'debts', 'utilities', 'meters', 'business', 'pocket_money', 'insurance', 'rental', 'receivables', 'travel_planner'];

    public const PRO_MODULES = ['savings', 'debts', 'utilities', 'meters', 'pocket_money', 'insurance', 'rental', 'receivables'];

    public const PREMIUM_MODULES = ['business', 'travel_planner'];

    public const PRO_FEATURES = ['private_wallet', 'utility_split'];

    public const PREMIUM_FEATURES = ['shopify_import', 'woocommerce_import', 'unas_import', 'ai', 'attachments', 'sumup_import'];

    public static function isBetaMode(): bool
    {
        return PlatformSettings::isBetaMode();
    }

    public static function billingTier(User $user): string
    {
        return HouseholdTierAccess::billingTier($user->household);
    }

    public static function effectiveTier(User $user): string
    {
        if ($user->lifetime_admin) {
            return self::TIER_PREMIUM;
        }

        if (self::isBetaMode()) {
            return self::TIER_PREMIUM;
        }

        return HouseholdTierAccess::accessTier($user->household);
    }

    public static function resolvedAccessTier(User $user): string
    {
        if ($user->lifetime_admin || self::isBetaMode()) {
            return self::TIER_PREMIUM;
        }

        return self::effectiveTier($user);
    }

    public static function canAccessModuleByTier(User $user, string $moduleId): bool
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

    public static function isHouseholdModuleEnabled(?Household $household, string $moduleId): bool
    {
        if ($household === null) {
            return false;
        }

        if ($moduleId === 'budget') {
            return (bool) ($household->budget_enabled ?? true);
        }

        $key = "{$moduleId}_enabled";

        return (bool) ($household->{$key} ?? false);
    }

    public static function canAccessModule(User $user, string $moduleId): bool
    {
        if ($user->lifetime_admin || self::isBetaMode()) {
            return true;
        }

        if (! PlatformModules::isReleased($moduleId)) {
            return false;
        }

        if (! self::canAccessModuleByTier($user, $moduleId)) {
            return false;
        }

        if (! self::isHouseholdModuleEnabled($user->household, $moduleId)) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return in_array($moduleId, $user->permissions ?? [], true);
    }

    public static function canUseFeature(User $user, string $featureId): bool
    {
        if ($user->lifetime_admin || self::isBetaMode()) {
            return true;
        }

        $tier = self::effectiveTier($user);

        if (in_array($featureId, self::PRO_FEATURES, true)) {
            return in_array($tier, [self::TIER_PRO, self::TIER_PREMIUM], true);
        }

        if (in_array($featureId, self::PREMIUM_FEATURES, true)) {
            return $tier === self::TIER_PREMIUM;
        }

        return false;
    }

    public static function canCreatePrivateWallet(User $user): bool
    {
        return self::canUseFeature($user, 'private_wallet');
    }

    public static function moduleAccessDeniedMessage(string $moduleId): string
    {
        if ($moduleId === 'travel_planner') {
            return 'Az utazástervező modul csak Premium előfizetéssel érhető el.';
        }

        if (in_array($moduleId, self::PREMIUM_MODULES, true)) {
            return 'A Vállalkozás modul csak Premium előfizetéssel érhető el.';
        }

        if (in_array($moduleId, self::PRO_MODULES, true)) {
            return 'Ez a modul csak Pro vagy Premium előfizetéssel érhető el.';
        }

        return 'Ehhez a modulhoz magasabb előfizetési csomag szükséges.';
    }

    public static function featureAccessDeniedMessage(string $featureId): string
    {
        return match ($featureId) {
            'ai' => 'Az AI funkciók csak Premium előfizetéssel érhetők el.',
            'shopify_import' => 'A Shopify import csak Premium előfizetéssel érhető el.',
            'sumup_import' => 'A SumUp könyvelési import csak Premium előfizetéssel érhető el.',
            'attachments' => 'A dokumentum és nyugta csatolás csak Premium előfizetéssel érhető el.',
            'private_wallet' => 'A privát kassza csak Pro vagy Premium előfizetéssel érhető el.',
            'utility_split' => 'A rezsimegosztás csak Pro vagy Premium előfizetéssel érhető el.',
            default => 'Ehhez a funkcióhoz magasabb előfizetési csomag szükséges.',
        };
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
