<?php

namespace App\Support;

class BudgetSettings
{
    public static function defaults(): array
    {
        return config('budget.defaults', []);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();

        if (! is_array($stored)) {
            return $defaults;
        }

        $cloneMode = (string) ($stored['clone_mode'] ?? $defaults['clone_mode']);
        if (! in_array($cloneMode, ['all', 'budget_only', 'fixed_recurring'], true)) {
            $cloneMode = $defaults['clone_mode'];
        }

        $groups = [];
        foreach ($stored['category_groups'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $color = trim((string) ($row['color'] ?? '#64748b'));
            if (! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                $color = '#64748b';
            }
            $categories = [];
            foreach ($row['categories'] ?? [] as $cat) {
                $c = trim((string) $cat);
                if ($c !== '') {
                    $categories[] = $c;
                }
            }
            $groups[] = [
                'name' => $name,
                'color' => $color,
                'categories' => array_values(array_unique($categories)),
            ];
        }

        $graceDays = max(0, min(60, (int) ($stored['missed_income_grace_days'] ?? $defaults['missed_income_grace_days'])));

        $categoryColors = [];
        $rawColors = $stored['category_colors'] ?? [];
        if (is_array($rawColors)) {
            foreach ($rawColors as $cat => $color) {
                $name = trim((string) $cat);
                if ($name === '') {
                    continue;
                }
                $hex = trim((string) $color);
                if (preg_match('/^#[0-9A-Fa-f]{6}$/', $hex)) {
                    $categoryColors[$name] = $hex;
                }
            }
        }
        foreach ($groups as $group) {
            foreach ($group['categories'] as $cat) {
                if (! isset($categoryColors[$cat])) {
                    $categoryColors[$cat] = $group['color'];
                }
            }
        }

        return [
            'category_groups' => $groups,
            'category_colors' => $categoryColors,
            'clone_mode' => $cloneMode,
            'missed_income_enabled' => array_key_exists('missed_income_enabled', $stored)
                ? (bool) $stored['missed_income_enabled']
                : (bool) $defaults['missed_income_enabled'],
            'missed_income_grace_days' => $graceDays,
            'default_currency' => self::normalizeCurrency($stored['default_currency'] ?? $defaults['default_currency'] ?? 'HUF'),
        ];
    }

    private static function normalizeCurrency(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));

        return in_array($currency, ['HUF', 'EUR', 'USD'], true) ? $currency : 'HUF';
    }
}
