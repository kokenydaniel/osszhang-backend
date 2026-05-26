<?php

namespace App\Support;

use App\Models\FeatureFlag;

final class PlatformSettings
{
    public const KEY_BETA_MODE = 'beta_mode';

    public static function isBetaMode(): bool
    {
        return FeatureFlags::isEnabled(self::KEY_BETA_MODE, false);
    }

    public static function setBetaMode(bool $enabled): void
    {
        FeatureFlag::query()->updateOrCreate(
            ['key' => self::KEY_BETA_MODE],
            [
                'value' => $enabled,
                'description' => 'Béta mód — tier korlátozások és Stripe számlázás kikapcsolása minden háztartásra.',
            ],
        );

        FeatureFlags::clearCache();
    }

    public static function clearCache(): void
    {
        // Kept for backward compatibility with callers that reset platform caches.
    }
}
