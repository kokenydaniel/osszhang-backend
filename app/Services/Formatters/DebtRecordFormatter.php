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
            'walletId' => $d->wallet_id,
            'wallet_id' => $d->wallet_id,
            'name' => (string) ($s['name'] ?? ''),
            'targetAmount' => (float) ($s['target_amount'] ?? 0),
            'paidAmount' => (float) ($s['paid_amount'] ?? 0),
            'annualInterestRate' => isset($s['annual_interest_rate']) ? (float) $s['annual_interest_rate'] : null,
            'minimumPayment' => isset($s['minimum_payment']) ? (float) $s['minimum_payment'] : null,
            'dueDay' => isset($s['due_day']) ? (int) $s['due_day'] : null,
            'status' => (string) ($s['status'] ?? ''),
            'budgetSyncEnabled' => (bool) ($s['budget_sync_enabled'] ?? false),
            'budgetStartYear' => isset($s['budget_start_year']) ? (int) $s['budget_start_year'] : null,
            'budgetStartMonth' => isset($s['budget_start_month']) ? (int) $s['budget_start_month'] : null,
            'paidInstallmentMonths' => array_values($s['paid_installment_months'] ?? []),
            'installmentPayments' => $this->formatInstallmentPayments($s),
            'attachmentCount' => (int) ($d->attachments_count ?? $d->attachments()->count()),
        ];
    }

    private function formatInstallmentPayments(array $s): array
    {
        $raw = $s['installment_payments'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $period = (string) ($row['period'] ?? '');
            if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
                continue;
            }
            $paidAt = $row['paid_at'] ?? $row['paidAt'] ?? null;
            $source = (string) ($row['source'] ?? 'budget');
            if (! in_array($source, ['budget', 'debt_pay'], true)) {
                $source = 'budget';
            }
            $out[] = [
                'period' => $period,
                'paidAt' => is_string($paidAt) && $paidAt !== '' ? $paidAt : null,
                'amount' => (float) ($row['amount'] ?? 0),
                'source' => $source,
                'note' => isset($row['note']) && is_string($row['note']) && trim($row['note']) !== '' ? trim($row['note']) : null,
            ];
        }

        usort($out, fn ($a, $b) => strcmp((string) $b['period'], (string) $a['period']));

        return $out;
    }
}
