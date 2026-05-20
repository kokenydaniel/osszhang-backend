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

    /** @param Collection<int, Utility> $bills */
    public function compute(Collection $bills, User $viewer, bool $splitEnabled, Household $household, EncryptedRecordService $crypto): array
    {
        $isAdmin = $viewer->role === 'admin';

        $partnerOwesUs = 0.0;
        $weOwePartner = 0.0;
        $wePaidTotal = 0.0;
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

            $wePaid = $isAdmin ? $paidBy === 'Mi' : $paidBy === 'Ildi';
            $partnerPaid = $isAdmin ? $paidBy === 'Ildi' : $paidBy === 'Mi';
            $isOurPrivate = $isAdmin ? $splitRule === 'dani-private' : $splitRule === 'ildi-private';
            $isPartnerPrivate = $isAdmin ? $splitRule === 'ildi-private' : $splitRule === 'dani-private';

            if ($wePaid) {
                $wePaidTotal += $total;
                if ($splitRule === 'shared') {
                    $partnerOwesUs += $total / 2;
                } elseif ($isPartnerPrivate) {
                    $partnerOwesUs += $total;
                }
            } elseif ($partnerPaid) {
                $partnerPaidTotal += $total;
                if ($splitRule === 'shared') {
                    $weOwePartner += $total / 2;
                } elseif ($isOurPrivate) {
                    $weOwePartner += $total;
                }
            }
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
