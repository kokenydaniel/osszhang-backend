<?php

namespace App\Support;

class InsuranceSettings
{
    public static function defaults(): array
    {
        return config('insurance.defaults', []);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();
        if (! is_array($stored)) {
            return $defaults;
        }

        $reminder = (int) ($stored['reminder_days_before'] ?? $stored['reminderDaysBefore'] ?? $defaults['reminder_days_before'] ?? 30);
        $reminder = max(1, min(365, $reminder));

        $currencies = $stored['currencies'] ?? $defaults['currencies'];
        if (! is_array($currencies) || $currencies === []) {
            $currencies = $defaults['currencies'];
        }
        $currencies = array_values(array_unique(array_map(
            fn ($c) => strtoupper(trim((string) $c)),
            $currencies,
        )));

        $defaultCurrency = strtoupper(trim((string) ($stored['default_currency'] ?? $defaults['default_currency'] ?? 'HUF')));
        if (! in_array($defaultCurrency, $currencies, true)) {
            $defaultCurrency = $currencies[0] ?? 'HUF';
        }

        $categoryPattern = trim((string) (
            $stored['payment_category_pattern']
            ?? $stored['paymentCategoryPattern']
            ?? $defaults['payment_category_pattern']
            ?? 'biztosít'
        ));

        return [
            'reminder_days_before' => $reminder,
            'currencies' => $currencies,
            'default_currency' => $defaultCurrency,
            'payment_category_pattern' => $categoryPattern !== '' ? $categoryPattern : 'biztosít',
            'budget_sync_default' => (bool) (
                $stored['budget_sync_default']
                ?? $stored['budgetSyncDefault']
                ?? $defaults['budget_sync_default']
                ?? false
            ),
        ];
    }
}
