<?php

namespace App\Services;

use App\Models\Household;
use App\Models\Transaction;

class TransactionSensitiveData
{
    public function __construct(
        private readonly HouseholdCipherService $cipher,
    ) {}

    public function packForStorage(Household $household, array $sensitive): string
    {
        return $this->cipher->encrypt($household, $sensitive);
    }

    public function resolve(Transaction $transaction, ?Household $household = null): array
    {
        $household = $household ?? $transaction->household;

        if ($transaction->encrypted_payload && $household) {
            try {
                return $this->cipher->decrypt($household, $transaction->encrypted_payload);
            } catch (\Throwable) {
            }
        }

        return [
            'description' => $transaction->description,
            'category' => $transaction->category,
            'amount' => (float) $transaction->amount,
            'subItems' => $transaction->relationLoaded('items')
                ? $transaction->items->map(fn ($i) => [
                    'id' => $i->id,
                    'date' => $i->date,
                    'amount' => (float) $i->amount,
                    'reason' => $i->reason,
                ])->values()->all()
                : [],
        ];
    }

    public function formatApi(Transaction $transaction, ?Household $household = null): array
    {
        $sensitive = $this->resolve($transaction, $household);

        return [
            'id' => $transaction->id,
            'walletId' => $transaction->wallet_id,
            'type' => $transaction->type,
            'description' => (string) ($sensitive['description'] ?? ''),
            'category' => (string) ($sensitive['category'] ?? ''),
            'amount' => (float) ($sensitive['amount'] ?? 0),
            'dueDate' => $transaction->due_date,
            'paidDate' => $transaction->paid_date,
            'isBudget' => (bool) $transaction->is_budget,
            'isReserve' => (bool) $transaction->is_reserve,
            'currency' => (string) ($transaction->currency ?? 'HUF'),
            'subItems' => collect($sensitive['subItems'] ?? [])->map(fn ($i) => [
                'id' => $i['id'] ?? 0,
                'date' => $i['date'],
                'amount' => (float) $i['amount'],
                'reason' => $i['reason'],
            ])->values()->all(),
        ];
    }

    public function persistSensitive(Transaction $transaction, Household $household, array $sensitive): void
    {
        $transaction->encrypted_payload = $this->packForStorage($household, $sensitive);
        $transaction->description = '—';
        $transaction->category = '—';
        $transaction->amount = 0;
    }

    public function resolvedAmount(Transaction $transaction, ?Household $household = null): float
    {
        $sensitive = $this->resolve($transaction, $household);

        return (float) ($sensitive['amount'] ?? 0);
    }

    public function resolvedCategory(Transaction $transaction, ?Household $household = null): string
    {
        return (string) ($this->resolve($transaction, $household)['category'] ?? '');
    }

    public function paidExpenseTotal(Transaction $transaction, ?Household $household = null): float
    {
        $sensitive = $this->resolve($transaction, $household);
        $subItems = $sensitive['subItems'] ?? [];
        if ($transaction->is_budget && count($subItems) > 0) {
            return (float) collect($subItems)->sum(fn ($i) => abs((float) ($i['amount'] ?? 0)));
        }

        return $transaction->paid_date ? (float) ($sensitive['amount'] ?? 0) : 0.0;
    }

    public function expenseTotal(Transaction $transaction, ?Household $household = null): float
    {
        $sensitive = $this->resolve($transaction, $household);
        $subItems = $sensitive['subItems'] ?? [];
        if ($transaction->is_budget && count($subItems) > 0) {
            return (float) collect($subItems)->sum(fn ($i) => abs((float) ($i['amount'] ?? 0)));
        }

        return (float) ($sensitive['amount'] ?? 0);
    }
}
