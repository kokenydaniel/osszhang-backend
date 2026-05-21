<?php

namespace App\Support;

class DebtsSettings
{
    public static function defaults(): array
    {
        return config('debts.defaults', []);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        $strategy = (string) ($stored['default_strategy'] ?? $defaults['default_strategy']);
        if (! in_array($strategy, ['avalanche', 'snowball'], true)) {
            $strategy = $defaults['default_strategy'];
        }

        $pattern = trim((string) ($stored['payment_category_pattern'] ?? $defaults['payment_category_pattern']));
        if ($pattern === '') {
            $pattern = $defaults['payment_category_pattern'];
        }

        return [
            'default_strategy' => $strategy,
            'default_extra_monthly' => max(0, (int) ($stored['default_extra_monthly'] ?? $defaults['default_extra_monthly'])),
            'pay_add_to_budget_default' => array_key_exists('pay_add_to_budget_default', $stored)
                ? (bool) $stored['pay_add_to_budget_default']
                : (bool) $defaults['pay_add_to_budget_default'],
            'payment_category_pattern' => $pattern,
        ];
    }
}
