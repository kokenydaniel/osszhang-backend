<?php

namespace App\Support;

use App\Models\FeatureFlag;

final class FeatureFlags
{
    /** @var array<string, bool> */
    private static array $cache = [];

    public static function isEnabled(string $key, bool $default = false): bool
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        $flag = FeatureFlag::query()->where('key', $key)->first();
        $value = $flag ? (bool) $flag->value : $default;
        self::$cache[$key] = $value;

        return $value;
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    /** @return array<string, bool> */
    public static function allEnabled(): array
    {
        $flags = FeatureFlag::query()->orderBy('key')->get();
        $out = [];
        foreach ($flags as $flag) {
            $out[$flag->key] = (bool) $flag->value;
            self::$cache[$flag->key] = (bool) $flag->value;
        }

        return $out;
    }
}
