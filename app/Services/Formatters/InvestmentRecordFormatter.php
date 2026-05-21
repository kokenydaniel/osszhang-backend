<?php

namespace App\Services\Formatters;

use App\Models\Household;
use App\Models\Investment;

class InvestmentRecordFormatter extends AbstractEncryptedRecordFormatter
{
    public function investmentLegacy(Investment $i): array
    {
        return [
            'name' => (string) $i->name,
            'type' => (string) $i->type,
            'principal_amount' => (float) $i->principal_amount,
            'annual_interest_rate' => (float) $i->annual_interest_rate,
            'owner' => (string) $i->owner,
            'current_value' => $i->current_value !== null ? (float) $i->current_value : null,
            'maturity_amount' => $i->maturity_amount !== null ? (float) $i->maturity_amount : null,
            'next_payout_amount' => $i->next_payout_amount !== null ? (float) $i->next_payout_amount : null,
        ];
    }

    public function investmentResolved(Investment $i, Household $household): array
    {
        return $this->resolve($household, $i->encrypted_payload, $this->investmentLegacy($i));
    }

    public function persistInvestment(Investment $i, Household $household, array $sensitive): void
    {
        $this->persist($household, $i, $sensitive, [
            'name' => '—',
            'type' => '—',
            'principal_amount' => 0,
            'annual_interest_rate' => 0,
            'owner' => '—',
            'current_value' => null,
            'maturity_amount' => null,
            'next_payout_amount' => null,
        ]);
    }

    public function formatInvestment(Investment $i, Household $household): array
    {
        $s = $this->investmentResolved($i, $household);

        return [
            'id' => $i->id,
            'name' => (string) ($s['name'] ?? ''),
            'type' => (string) ($s['type'] ?? 'bond'),
            'principalAmount' => (float) ($s['principal_amount'] ?? 0),
            'annualInterestRate' => (float) ($s['annual_interest_rate'] ?? 0),
            'purchaseDate' => $i->purchase_date->toDateString(),
            'maturityDate' => $i->maturity_date ? $i->maturity_date->toDateString() : null,
            'owner' => (string) ($s['owner'] ?? 'Közös'),
            'countInSavings' => (bool) $i->count_in_savings,
            'currentValue' => isset($s['current_value']) ? (float) $s['current_value'] : null,
            'maturityAmount' => isset($s['maturity_amount']) ? (float) $s['maturity_amount'] : null,
            'nextPayoutAmount' => isset($s['next_payout_amount']) ? (float) $s['next_payout_amount'] : null,
            'nextPayoutDate' => $i->next_payout_date ? $i->next_payout_date->toDateString() : null,
        ];
    }
}
