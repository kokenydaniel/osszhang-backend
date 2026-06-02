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

        $templates = array_key_exists('debt_type_templates', $stored)
            ? self::normalizeTemplates($stored['debt_type_templates'])
            : ($defaults['debt_type_templates'] ?? []);

        return [
            'default_strategy' => $strategy,
            'default_extra_monthly' => max(0, (int) ($stored['default_extra_monthly'] ?? $defaults['default_extra_monthly'])),
            'pay_add_to_budget_default' => array_key_exists('pay_add_to_budget_default', $stored)
                ? (bool) $stored['pay_add_to_budget_default']
                : (bool) $defaults['pay_add_to_budget_default'],
            'payment_category_pattern' => $pattern,
            'reminder_days_before' => max(0, min(60, (int) ($stored['reminder_days_before'] ?? $defaults['reminder_days_before']))),
            'default_interest_rate_annual' => max(0, min(100, (float) ($stored['default_interest_rate_annual'] ?? $defaults['default_interest_rate_annual']))),
            'debt_type_templates' => $templates,
        ];
    }

    /** @param  mixed  $rows */
    private static function normalizeTemplates($rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $templates = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $templates[] = [
                'label' => $label,
                'default_interest_rate_annual' => max(0, min(100, (float) ($row['default_interest_rate_annual'] ?? 0))),
            ];
        }

        return $templates;
    }
}
