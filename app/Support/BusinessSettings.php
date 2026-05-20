<?php

namespace App\Support;

class BusinessSettings
{
    public static function defaults(): array
    {
        return config('business.defaults', [
            'channels' => [],
            'payment_methods' => [],
            'providers' => [],
            'destinations' => [],
        ]);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        return [
            'channels' => self::normalizeList($stored['channels'] ?? null, $defaults['channels']),
            'payment_methods' => self::normalizeList($stored['payment_methods'] ?? null, $defaults['payment_methods']),
            'providers' => self::normalizeList($stored['providers'] ?? null, $defaults['providers']),
            'destinations' => self::normalizeList($stored['destinations'] ?? null, $defaults['destinations']),
        ];
    }

    public static function normalizeList(?array $list, array $fallback): array
    {
        if (! is_array($list)) {
            return $fallback;
        }

        $clean = array_values(array_unique(array_filter(array_map(
            fn ($v) => trim((string) $v),
            $list
        ))));

        return count($clean) > 0 ? $clean : $fallback;
    }

    public static function shopifyChannelLabel(array $settings): string
    {
        foreach ($settings['channels'] as $channel) {
            if (stripos($channel, 'shopify') !== false || stripos($channel, 'webshop') !== false) {
                return $channel;
            }
        }

        return $settings['channels'][0] ?? 'Webshop (Shopify)';
    }
}
