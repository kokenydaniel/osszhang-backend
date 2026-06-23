<?php

namespace App\Support;

class RentalSettings
{

    public static function resolve(?array $stored): array
    {
        $defaults = config('rental.defaults', []);

        $currencies = $stored['currencies'] ?? $defaults['currencies'] ?? ['HUF', 'EUR'];
        if (! is_array($currencies)) {
            $currencies = ['HUF', 'EUR'];
        }
        $currencies = array_values(array_unique(array_map(
            fn ($c) => strtoupper(trim((string) $c)),
            $currencies,
        )));
        if ($currencies === []) {
            $currencies = ['HUF', 'EUR'];
        }

        $defaultCurrency = strtoupper(trim((string) (
            $stored['default_currency'] ?? $stored['defaultCurrency'] ?? $defaults['default_currency'] ?? 'HUF'
        )));

        $reminder = (int) (
            $stored['contract_reminder_days_before']
            ?? $stored['contractReminderDaysBefore']
            ?? $defaults['contract_reminder_days_before']
            ?? 60
        );

        $grace = (int) (
            $stored['overdue_grace_days']
            ?? $stored['overdueGraceDays']
            ?? $defaults['overdue_grace_days']
            ?? 0
        );

        $budgetSyncDefault = (bool) (
            $stored['budget_sync_default']
            ?? $stored['budgetSyncDefault']
            ?? $defaults['budget_sync_default']
            ?? false
        );

        $incomePattern = trim((string) (
            $stored['income_category_pattern']
            ?? $stored['incomeCategoryPattern']
            ?? $defaults['income_category_pattern']
            ?? 'bérlet'
        ));

        return [
            'currencies' => $currencies,
            'default_currency' => in_array($defaultCurrency, $currencies, true) ? $defaultCurrency : $currencies[0],
            'contract_reminder_days_before' => max(1, min(365, $reminder)),
            'overdue_grace_days' => max(0, min(30, $grace)),
            'budget_sync_default' => $budgetSyncDefault,
            'income_category_pattern' => $incomePattern !== '' ? $incomePattern : 'bérlet',
        ];
    }
}
