<?php

namespace App\Support;

class UtilityTemplates
{
    public static function defaults(): array
    {
        return config('utilities.default_templates', []);
    }

    public static function resolve(?array $stored): array
    {
        if (! is_array($stored)) {
            return self::defaults();
        }

        $out = [];
        foreach ($stored as $row) {
            if (! is_array($row)) {
                continue;
            }
            $type = trim((string) ($row['type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $dueDay = (int) ($row['due_day'] ?? $row['dueDay'] ?? 15);
            $dueDay = max(1, min(28, $dueDay));
            $split = (string) ($row['split_rule'] ?? $row['splitRule'] ?? 'shared');
            if (! in_array($split, ['shared', 'dani-private', 'ildi-private'], true)) {
                $split = 'shared';
            }
            $provider = trim((string) ($row['provider'] ?? ''));
            $paymentMethod = trim((string) ($row['payment_method'] ?? $row['paymentMethod'] ?? ''));
            $budgetCategory = trim((string) ($row['budget_category'] ?? $row['budgetCategory'] ?? ''));

            $out[] = array_filter([
                'type' => $type,
                'total' => max(0, (float) ($row['total'] ?? 0)),
                'due_day' => $dueDay,
                'split_rule' => $split,
                'provider' => $provider !== '' ? $provider : null,
                'payment_method' => $paymentMethod !== '' ? $paymentMethod : null,
                'budget_category' => $budgetCategory !== '' ? $budgetCategory : null,
            ], fn ($v) => $v !== null);
        }

        return $out;
    }
}
