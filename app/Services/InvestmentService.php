<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Investment;

class InvestmentService
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function listForHousehold(Household $household): array
    {
        return Investment::where('household_id', $household->id)
            ->get()
            ->map(fn ($i) => $this->crypto->formatInvestment($i, $household))
            ->all();
    }

    public function create(Household $household, array $validated): array
    {
        $i = new Investment([
            'household_id' => $household->id,
            'purchase_date' => $validated['purchaseDate'],
            'maturity_date' => $validated['maturityDate'] ?? null,
            'count_in_savings' => $validated['countInSavings'] ?? true,
            'next_payout_date' => $validated['nextPayoutDate'] ?? null,
        ]);
        $this->crypto->persistInvestment($i, $household, [
            'name' => $validated['name'],
            'type' => $validated['type'] ?? 'bond',
            'principal_amount' => (float) $validated['principalAmount'],
            'annual_interest_rate' => (float) $validated['annualInterestRate'],
            'owner' => $validated['owner'] ?? 'Közös',
            'current_value' => $validated['currentValue'] ?? null,
            'maturity_amount' => $validated['maturityAmount'] ?? null,
            'next_payout_amount' => $validated['nextPayoutAmount'] ?? null,
        ]);
        $i->save();

        return $this->crypto->formatInvestment($i, $household);
    }

    public function update(Household $household, int|string $id, array $validated): array
    {
        $i = Investment::where('household_id', $household->id)->findOrFail($id);

        $sensitive = $this->crypto->investmentResolved($i, $household);
        if (array_key_exists('name', $validated)) {
            $sensitive['name'] = $validated['name'];
        }
        if (array_key_exists('type', $validated)) {
            $sensitive['type'] = $validated['type'];
        }
        if (array_key_exists('principalAmount', $validated)) {
            $sensitive['principal_amount'] = (float) $validated['principalAmount'];
        }
        if (array_key_exists('annualInterestRate', $validated)) {
            $sensitive['annual_interest_rate'] = (float) $validated['annualInterestRate'];
        }
        if (array_key_exists('owner', $validated)) {
            $sensitive['owner'] = $validated['owner'];
        }
        if (array_key_exists('currentValue', $validated)) {
            $sensitive['current_value'] = $validated['currentValue'];
        }
        if (array_key_exists('maturityAmount', $validated)) {
            $sensitive['maturity_amount'] = $validated['maturityAmount'];
        }
        if (array_key_exists('nextPayoutAmount', $validated)) {
            $sensitive['next_payout_amount'] = $validated['nextPayoutAmount'];
        }
        if (array_key_exists('purchaseDate', $validated)) {
            $i->purchase_date = $validated['purchaseDate'];
        }
        if (array_key_exists('maturityDate', $validated)) {
            $i->maturity_date = $validated['maturityDate'];
        }
        if (array_key_exists('nextPayoutDate', $validated)) {
            $i->next_payout_date = $validated['nextPayoutDate'];
        }
        if (array_key_exists('countInSavings', $validated)) {
            $i->count_in_savings = $validated['countInSavings'];
        }

        $this->crypto->persistInvestment($i, $household, $sensitive);
        $i->save();

        return $this->crypto->formatInvestment($i, $household);
    }

    public function delete(int $householdId, int|string $id): void
    {
        Investment::where('household_id', $householdId)->findOrFail($id)->delete();
    }
}
