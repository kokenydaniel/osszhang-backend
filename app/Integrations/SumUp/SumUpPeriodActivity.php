<?php

namespace App\Integrations\SumUp;

final class SumUpPeriodActivity
{

    public static function hasSalesTransactions(array $transactions): bool
    {
        foreach ($transactions as $tx) {
            if (! is_array($tx)) {
                continue;
            }

            $amount = (float) ($tx['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $type = strtoupper((string) ($tx['type'] ?? ''));
            $status = strtoupper((string) ($tx['status'] ?? ''));

            if ($type === 'PAYMENT' && $status === 'SUCCESSFUL') {
                return true;
            }

            if ($type === 'REFUND' && in_array($status, ['SUCCESSFUL', 'REFUNDED'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function hasPayoutRecords(array $payouts): bool
    {
        return count($payouts) > 0;
    }
}
