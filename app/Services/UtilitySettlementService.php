<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\UtilitySettlement;

class UtilitySettlementService
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function revert(UtilitySettlement $settlement, Household $household, bool $deleteTransaction = true): void
    {
        $resolved = $this->crypto->settlementResolved($settlement, $household);
        $amount = (float) ($resolved['amount'] ?? 0);
        $direction = (string) ($resolved['direction'] ?? $settlement->direction);

        if ($direction === 'partner_pays_household') {
            $this->crypto->adjustManualBalance($household, -$amount);
        } else {
            $this->crypto->adjustManualBalance($household, $amount);
        }

        if ($deleteTransaction && $settlement->transaction_id) {
            Transaction::where('household_id', $household->id)
                ->where('id', $settlement->transaction_id)
                ->delete();
        }

        $settlement->delete();
    }
}
