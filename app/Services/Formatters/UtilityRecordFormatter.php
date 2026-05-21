<?php

namespace App\Services\Formatters;

use App\Models\Household;
use App\Models\Utility;
use App\Models\UtilitySettlement;

class UtilityRecordFormatter extends AbstractEncryptedRecordFormatter
{
    public function utilityLegacy(Utility $u): array
    {
        return [
            'type' => (string) $u->type,
            'total' => (float) $u->total,
            'paid_by' => $u->paid_by,
            'split_rule' => (string) ($u->split_rule ?? 'shared'),
        ];
    }

    public function utilityResolved(Utility $u, Household $household): array
    {
        return $this->resolve($household, $u->encrypted_payload, $this->utilityLegacy($u));
    }

    public function persistUtility(Utility $u, Household $household, array $sensitive): void
    {
        $this->persist($household, $u, $sensitive, [
            'type' => '—',
            'total' => 0,
            'paid_by' => null,
            'split_rule' => 'shared',
        ]);
    }

    public function formatUtility(Utility $u, Household $household): array
    {
        $s = $this->utilityResolved($u, $household);

        return [
            'id' => $u->id,
            'type' => (string) ($s['type'] ?? ''),
            'total' => (float) ($s['total'] ?? 0),
            'dueDate' => $u->due_date,
            'paidDate' => $u->paid_date,
            'paidBy' => $s['paid_by'] ?? null,
            'splitRule' => (string) ($s['split_rule'] ?? 'shared'),
        ];
    }

    public function settlementLegacy(UtilitySettlement $s): array
    {
        return [
            'amount' => (float) $s->amount,
            'direction' => (string) $s->direction,
        ];
    }

    public function settlementResolved(UtilitySettlement $s, Household $household): array
    {
        return $this->resolve($household, $s->encrypted_payload, $this->settlementLegacy($s));
    }

    public function persistSettlement(UtilitySettlement $s, Household $household, array $sensitive): void
    {
        $this->persist($household, $s, $sensitive, [
            'amount' => 0,
            'direction' => $sensitive['direction'] ?? 'partner_pays_household',
        ]);
    }
}
