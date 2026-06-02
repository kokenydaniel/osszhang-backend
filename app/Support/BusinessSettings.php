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

        $priceMode = (string) ($stored['price_input_mode'] ?? $defaults['price_input_mode']);
        if (! in_array($priceMode, ['net', 'gross'], true)) {
            $priceMode = $defaults['price_input_mode'];
        }

        $schedule = (string) ($stored['shopify_sync_schedule'] ?? $defaults['shopify_sync_schedule']);
        if (! in_array($schedule, ['off', 'hourly', 'every_6_hours', 'daily'], true)) {
            $schedule = $defaults['shopify_sync_schedule'];
        }

        $statuses = self::normalizeList($stored['order_statuses'] ?? null, $defaults['order_statuses'] ?? []);
        if (count($statuses) === 0) {
            $statuses = $defaults['order_statuses'] ?? [];
        }

        $lastSynced = $stored['shopify_last_synced_at'] ?? $defaults['shopify_last_synced_at'];
        $lastSynced = is_string($lastSynced) && $lastSynced !== '' ? $lastSynced : null;

        return [
            'channels' => self::normalizeList($stored['channels'] ?? null, $defaults['channels']),
            'payment_methods' => self::normalizeList($stored['payment_methods'] ?? null, $defaults['payment_methods']),
            'providers' => self::normalizeList($stored['providers'] ?? null, $defaults['providers']),
            'destinations' => self::normalizeList($stored['destinations'] ?? null, $defaults['destinations']),
            'default_vat_percent' => max(0, min(100, (float) ($stored['default_vat_percent'] ?? $defaults['default_vat_percent']))),
            'price_input_mode' => $priceMode,
            'order_statuses' => $statuses,
            'shopify_sync_schedule' => $schedule,
            'shopify_last_synced_at' => $lastSynced,
        ];
    }

    public static function normalizeList(?array $list, array $fallback): array
    {
        if ($list === null) {
            return $fallback;
        }

        if (! is_array($list)) {
            return $fallback;
        }

        $clean = array_values(array_unique(array_filter(array_map(
            fn ($v) => trim((string) $v),
            $list
        ))));

        return $clean;
    }

    public static function shopifyChannelLabel(array $settings): string
    {
        foreach ($settings['channels'] as $channel) {
            if (stripos($channel, 'shopify') !== false || stripos($channel, 'webshop') !== false) {
                return $channel;
            }
        }

        return $settings['channels'][0] ?? '';
    }

    public static function syncIntervalMinutes(string $schedule): ?int
    {
        return match ($schedule) {
            'hourly' => 60,
            'every_6_hours' => 360,
            'daily' => 1440,
            default => null,
        };
    }
}
