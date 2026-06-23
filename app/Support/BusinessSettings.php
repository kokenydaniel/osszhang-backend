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

        $sumupSynced = $stored['sumup_last_synced_at'] ?? $defaults['sumup_last_synced_at'] ?? null;
        $sumupSynced = is_string($sumupSynced) && $sumupSynced !== '' ? $sumupSynced : null;

        return [
            'channels' => self::normalizeList($stored['channels'] ?? null, $defaults['channels']),
            'payment_methods' => self::normalizeList($stored['payment_methods'] ?? null, $defaults['payment_methods']),
            'providers' => self::normalizeList($stored['providers'] ?? null, $defaults['providers']),
            'destinations' => self::normalizeList($stored['destinations'] ?? null, $defaults['destinations']),
            'default_vat_percent' => max(0, min(100, (float) ($stored['default_vat_percent'] ?? $defaults['default_vat_percent']))),
            'price_input_mode' => $priceMode,
            'order_statuses' => $statuses,
            'order_status_colors' => self::normalizeStatusColors(
                $stored['order_status_colors'] ?? null,
                $statuses,
                $defaults['order_status_colors'] ?? [],
            ),
            'shopify_sync_schedule' => $schedule,
            'shopify_last_synced_at' => $lastSynced,
            'sumup_last_synced_at' => $sumupSynced,
            'tax_regime' => self::normalizeTaxRegime($stored['tax_regime'] ?? $defaults['tax_regime'] ?? 'aam'),
            'income_tax_method' => self::normalizeIncomeTaxMethod(
                $stored['income_tax_method'] ?? $defaults['income_tax_method'] ?? 'cost_ratio',
            ),
            'cost_ratio_percent' => max(0, min(100, (float) ($stored['cost_ratio_percent'] ?? $defaults['cost_ratio_percent'] ?? 40))),
            'revenue_basis' => self::normalizeRevenueBasis($stored['revenue_basis'] ?? $defaults['revenue_basis'] ?? 'documented_only'),
        ];
    }

    private static function normalizeTaxRegime(mixed $value): string
    {
        $regime = (string) $value;

        return in_array($regime, ['aam', 'vat', 'kata'], true) ? $regime : 'aam';
    }

    private static function normalizeIncomeTaxMethod(mixed $value): string
    {
        $method = (string) $value;

        return in_array($method, ['cost_ratio', 'actual', 'kata_flat'], true) ? $method : 'cost_ratio';
    }

    private static function normalizeRevenueBasis(mixed $value): string
    {
        $basis = (string) $value;

        return in_array($basis, ['documented_only', 'all_orders'], true) ? $basis : 'documented_only';
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

    public static function normalizeStatusColors(?array $colors, array $statuses, array $defaults): array
    {
        $allowed = ['success', 'warning', 'danger', 'info', 'primary', 'neutral'];
        $out = [];

        foreach ($statuses as $status) {
            $candidate = is_array($colors) ? ($colors[$status] ?? null) : null;
            if (! is_string($candidate) || ! in_array($candidate, $allowed, true)) {
                $candidate = $defaults[$status] ?? 'neutral';
            }
            if (! in_array($candidate, $allowed, true)) {
                $candidate = 'neutral';
            }
            $out[$status] = $candidate;
        }

        return $out;
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
