<?php

namespace App\Services\Formatters;

use App\Models\Debt;
use App\Models\Household;

class DebtRecordFormatter extends AbstractEncryptedRecordFormatter
{
    public function debtLegacy(Debt $d): array
    {
        return [
            'name' => (string) $d->name,
            'target_amount' => (float) $d->target_amount,
            'paid_amount' => (float) $d->paid_amount,
            'annual_interest_rate' => $d->annual_interest_rate !== null ? (float) $d->annual_interest_rate : null,
            'minimum_payment' => $d->minimum_payment !== null ? (float) $d->minimum_payment : null,
            'due_day' => $d->due_day !== null ? (int) $d->due_day : null,
            'status' => (string) $d->status,
        ];
    }

    public function debtResolved(Debt $d, Household $household): array
    {
        return $this->resolve($household, $d->encrypted_payload, $this->debtLegacy($d));
    }

    public function persistDebt(Debt $d, Household $household, array $sensitive): void
    {
        $this->persist($household, $d, $sensitive, [
            'name' => '—',
            'target_amount' => 0,
            'paid_amount' => 0,
            'annual_interest_rate' => null,
            'minimum_payment' => null,
            'due_day' => null,
            'status' => 'Még fizetendő',
        ]);
    }

    public function formatDebt(Debt $d, Household $household): array
    {
        $s = $this->debtResolved($d, $household);

        return [
            'id' => $d->id,
            'name' => (string) ($s['name'] ?? ''),
            'targetAmount' => (float) ($s['target_amount'] ?? 0),
            'paidAmount' => (float) ($s['paid_amount'] ?? 0),
            'annualInterestRate' => isset($s['annual_interest_rate']) ? (float) $s['annual_interest_rate'] : null,
            'minimumPayment' => isset($s['minimum_payment']) ? (float) $s['minimum_payment'] : null,
            'dueDay' => isset($s['due_day']) ? (int) $s['due_day'] : null,
            'status' => (string) ($s['status'] ?? ''),
        ];
    }
}
