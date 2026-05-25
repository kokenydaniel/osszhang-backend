<?php

namespace App\Support;

use App\Models\AppSetting;

final class PlatformSettings
{
    public const KEY_BETA_MODE = 'beta_mode';

    private static ?bool $betaMode = null;

    public static function isBetaMode(): bool
    {
        if (self::$betaMode !== null) {
            return self::$betaMode;
        }

        $stored = AppSetting::query()->where('key', self::KEY_BETA_MODE)->value('value');
        self::$betaMode = filter_var($stored, FILTER_VALIDATE_BOOLEAN);

        return self::$betaMode;
    }

    public static function setBetaMode(bool $enabled): void
    {
        AppSetting::updateOrCreate(
            ['key' => self::KEY_BETA_MODE],
            ['value' => $enabled ? '1' : '0'],
        );

        self::$betaMode = $enabled;
    }

    public static function clearCache(): void
    {
        self::$betaMode = null;
    }
}
