<?php

namespace App\Services;

use App\Models\Debt;
use App\Models\Household;

class DebtService
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function listForHousehold(Household $household): array
    {
        return Debt::where('household_id', $household->id)
            ->get()
            ->map(fn ($d) => $this->crypto->formatDebt($d, $household))
            ->all();
    }

    public function create(Household $household, array $validated): array
    {
        $d = new Debt(['household_id' => $household->id]);
        $this->crypto->persistDebt($d, $household, [
            'name' => $validated['name'],
            'target_amount' => (float) $validated['targetAmount'],
            'paid_amount' => (float) ($validated['paidAmount'] ?? 0),
            'annual_interest_rate' => $validated['annualInterestRate'] ?? null,
            'minimum_payment' => $validated['minimumPayment'] ?? null,
            'due_day' => $validated['dueDay'] ?? null,
            'status' => $validated['status'] ?? 'Még fizetendő',
        ]);
        $d->save();

        return $this->crypto->formatDebt($d, $household);
    }

    public function update(Household $household, int|string $id, array $validated): array
    {
        $d = Debt::where('household_id', $household->id)->findOrFail($id);

        $sensitive = $this->crypto->debtResolved($d, $household);
        if (array_key_exists('name', $validated)) {
            $sensitive['name'] = $validated['name'];
        }
        if (array_key_exists('targetAmount', $validated)) {
            $sensitive['target_amount'] = (float) $validated['targetAmount'];
        }
        if (array_key_exists('paidAmount', $validated)) {
            $sensitive['paid_amount'] = (float) $validated['paidAmount'];
        }
        if (array_key_exists('annualInterestRate', $validated)) {
            $sensitive['annual_interest_rate'] = $validated['annualInterestRate'];
        }
        if (array_key_exists('minimumPayment', $validated)) {
            $sensitive['minimum_payment'] = $validated['minimumPayment'];
        }
        if (array_key_exists('dueDay', $validated)) {
            $sensitive['due_day'] = $validated['dueDay'];
        }
        if (array_key_exists('status', $validated)) {
            $sensitive['status'] = $validated['status'];
        }

        $this->crypto->persistDebt($d, $household, $sensitive);
        $d->save();

        return $this->crypto->formatDebt($d, $household);
    }

    public function delete(int $householdId, int|string $id): void
    {
        Debt::where('household_id', $householdId)->findOrFail($id)->delete();
    }
}
