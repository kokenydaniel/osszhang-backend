<?php

namespace App\Services;

use App\Models\Household;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Support\Collection;

class UtilityBalanceService
{
    public static function isLegacySettlementBill(Utility $utility, ?Household $household = null, ?EncryptedRecordService $crypto = null): bool
    {
        $type = $utility->type;
        if ($household && $crypto) {
            $type = (string) ($crypto->utilityResolved($utility, $household)['type'] ?? $type);
        }

        return stripos($type, 'kiegyenlít') !== false;
    }

    private function absoluteLedger(Collection $bills, bool $splitEnabled, Household $household, EncryptedRecordService $crypto): array
    {
        $householdReceivable = 0.0;
        $householdPayable = 0.0;
        $householdPaidTotal = 0.0;
        $partnerPaidTotal = 0.0;

        foreach ($bills as $bill) {
            if (self::isLegacySettlementBill($bill, $household, $crypto)) {
                continue;
            }

            if (! $splitEnabled) {
                continue;
            }

            $s = $crypto->utilityResolved($bill, $household);
            $total = (float) ($s['total'] ?? 0);
            $paidBy = $s['paid_by'] ?? null;
            $splitRule = (string) ($s['split_rule'] ?? 'shared');

            if ($paidBy === null) {
                continue;
            }

            $householdPaid = $paidBy === 'Mi';
            $partnerPaid = $paidBy === 'Ildi';

            if ($householdPaid) {
                $householdPaidTotal += $total;
            }
            if ($partnerPaid) {
                $partnerPaidTotal += $total;
            }

            if ($splitRule === 'shared') {
                if ($householdPaid) {
                    $householdReceivable += $total / 2;
                }
                if ($partnerPaid) {
                    $householdPayable += $total / 2;
                }
            } elseif ($splitRule === 'dani-private') {
                if ($partnerPaid) {
                    $householdPayable += $total;
                }
            } elseif ($splitRule === 'ildi-private') {
                if ($householdPaid) {
                    $householdReceivable += $total;
                }
            }
        }

        return [
            'household_receivable' => round($householdReceivable, 2),
            'household_payable' => round($householdPayable, 2),
            'household_paid_total' => round($householdPaidTotal, 2),
            'partner_paid_total' => round($partnerPaidTotal, 2),
        ];
    }

    public function compute(Collection $bills, User $viewer, bool $splitEnabled, Household $household, EncryptedRecordService $crypto): array
    {
        $ledger = $this->absoluteLedger($bills, $splitEnabled, $household, $crypto);

        $onHouseholdSide = $household->utility_split_partner_id === null
            || (int) $viewer->id !== (int) $household->utility_split_partner_id;

        if ($onHouseholdSide) {
            $partnerOwesUs = $ledger['household_receivable'];
            $weOwePartner = $ledger['household_payable'];
            $wePaidTotal = $ledger['household_paid_total'];
            $partnerPaidTotal = $ledger['partner_paid_total'];
        } else {
            $partnerOwesUs = $ledger['household_payable'];
            $weOwePartner = $ledger['household_receivable'];
            $wePaidTotal = $ledger['partner_paid_total'];
            $partnerPaidTotal = $ledger['household_paid_total'];
        }

        $netBalance = round($partnerOwesUs - $weOwePartner, 2);

        return [
            'partner_owes_us' => round($partnerOwesUs, 2),
            'we_owe_partner' => round($weOwePartner, 2),
            'we_paid_total' => round($wePaidTotal, 2),
            'partner_paid_total' => round($partnerPaidTotal, 2),
            'net_balance' => $netBalance,
        ];
    }
}
