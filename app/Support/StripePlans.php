<?php

namespace App\Support;

final class StripePlans
{
    /** @return list<string> */
    public static function allowedPriceIds(): array
    {
        return array_values(array_filter(config('stripe_plans.prices', [])));
    }

    public static function tierForPriceId(?string $priceId): ?string
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }

        return config('stripe_plans.price_tiers')[$priceId] ?? null;
    }

    public static function isAllowedPriceId(string $priceId): bool
    {
        return in_array($priceId, self::allowedPriceIds(), true);
    }
}
