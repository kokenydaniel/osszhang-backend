<?php

namespace App\Support;

class PocketMoneySettings
{
    public static function defaults(): array
    {
        return config('pocket_money.defaults', []);
    }

    public static function resolve(?array $stored): array
    {
        $defaults = self::defaults();
        if (! is_array($stored)) {
            return $defaults;
        }

        $currencies = $stored['currencies'] ?? $defaults['currencies'];
        if (! is_array($currencies) || $currencies === []) {
            $currencies = $defaults['currencies'];
        }
        $currencies = array_values(array_unique(array_map(
            fn ($c) => strtoupper(trim((string) $c)),
            $currencies,
        )));
        if ($currencies === []) {
            $currencies = $defaults['currencies'];
        }

        $defaultCurrency = strtoupper(trim((string) ($stored['default_currency'] ?? $defaults['default_currency'])));
        if (! in_array($defaultCurrency, $currencies, true)) {
            $defaultCurrency = $currencies[0];
        }

        $members = $stored['members'] ?? $defaults['members'] ?? [];
        if (! is_array($members)) {
            $members = [];
        }
        $members = array_values(array_filter(array_map(function ($row) {
            if (! is_array($row)) {
                return null;
            }
            $id = trim((string) ($row['id'] ?? ''));
            $label = trim((string) ($row['label'] ?? ''));
            if ($id === '' || $label === '') {
                return null;
            }
            $memberUserId = $row['member_user_id'] ?? $row['memberUserId'] ?? null;
            $memberUserId = $memberUserId !== null && $memberUserId !== '' ? (int) $memberUserId : null;
            $icon = trim((string) ($row['icon'] ?? 'star'));
            if ($icon === '') {
                $icon = 'star';
            }
            $stickerColor = self::normalizeHex($row['sticker_color'] ?? $row['stickerColor'] ?? null);
            $iconColor = self::normalizeHex($row['icon_color'] ?? $row['iconColor'] ?? null);

            return [
                'id' => mb_substr($id, 0, 64),
                'label' => mb_substr($label, 0, 100),
                'member_user_id' => $memberUserId,
                'icon' => mb_substr($icon, 0, 32),
                'sticker_color' => $stickerColor,
                'icon_color' => $iconColor,
            ];
        }, $members)));

        $interestEnabled = (bool) ($stored['interest_enabled'] ?? $stored['interestEnabled'] ?? $defaults['interest_enabled'] ?? false);
        $interestRate = (float) ($stored['interest_rate_percent'] ?? $stored['interestRatePercent'] ?? $defaults['interest_rate_percent'] ?? 10);
        $interestRate = max(0, min(100, $interestRate));
        $interestBasis = (string) ($stored['interest_basis'] ?? $stored['interestBasis'] ?? $defaults['interest_basis'] ?? 'no_expense');
        if (! in_array($interestBasis, ['no_expense', 'remaining'], true)) {
            $interestBasis = 'no_expense';
        }

        $interestOn = (string) ($stored['interest_on'] ?? $stored['interestOn'] ?? $defaults['interest_on'] ?? 'balance');
        if (! in_array($interestOn, ['balance', 'month_allowance'], true)) {
            $interestOn = 'balance';
        }

        return [
            'currencies' => $currencies,
            'default_currency' => $defaultCurrency,
            'members' => $members,
            'interest_enabled' => $interestEnabled,
            'interest_rate_percent' => $interestRate,
            'interest_on' => $interestOn,
            'interest_basis' => $interestBasis,
        ];
    }

    private static function normalizeHex(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $v = strtoupper(trim((string) $value));
        if (! preg_match('/^#[0-9A-F]{6}$/', $v)) {
            return null;
        }

        return $v;
    }
}
