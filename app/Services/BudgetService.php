<?php

namespace App\Services;

use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\UtilitySettlement;
use App\Models\User;

class BudgetService
{
    public function __construct(
        private readonly TransactionSensitiveData $sensitive,
        private readonly HouseholdCipherService $cipher,
        private readonly UtilitySettlementService $utilitySettlements,
    ) {}

    public function ensureHousehold(Household $household): Household
    {
        $this->cipher->ensureCipherKey($household);

        return $household;
    }

    public function listForHousehold(Household $household): array
    {
        $this->ensureHousehold($household);

        return Transaction::where('household_id', $household->id)
            ->with('items')
            ->orderBy('due_date', 'desc')
            ->get()
            ->map(fn ($t) => $this->sensitive->formatApi($t, $household))
            ->all();
    }

    public function show(Household $household, int|string $id): array
    {
        $this->ensureHousehold($household);
        $transaction = Transaction::where('household_id', $household->id)
            ->with('items')
            ->findOrFail($id);

        return $this->sensitive->formatApi($transaction, $household);
    }

    public function create(Household $household, User $user, array $validated): array
    {
        $this->ensureHousehold($household);

        $transaction = new Transaction([
            'household_id' => $household->id,
            'user_id' => $user->id,
            'type' => $validated['type'],
            'due_date' => $validated['dueDate'],
            'paid_date' => $validated['paidDate'] ?? null,
            'is_budget' => $validated['isBudget'] ?? false,
            'is_reserve' => $validated['isReserve'] ?? false,
        ]);

        $this->sensitive->persistSensitive($transaction, $household, [
            'description' => $validated['description'],
            'category' => $validated['category'],
            'amount' => (float) $validated['amount'],
            'subItems' => [],
        ]);
        $transaction->save();

        return $this->sensitive->formatApi($transaction->load('items'), $household);
    }

    public function update(Household $household, int|string $id, array $input): array
    {
        $this->ensureHousehold($household);
        $transaction = Transaction::where('household_id', $household->id)
            ->with('items')
            ->findOrFail($id);

        $current = $this->sensitive->resolve($transaction, $household);

        if (array_key_exists('description', $input)) {
            $current['description'] = $input['description'];
        }
        if (array_key_exists('type', $input)) {
            $transaction->type = $input['type'];
        }
        if (array_key_exists('amount', $input)) {
            $current['amount'] = (float) $input['amount'];
        }
        if (array_key_exists('category', $input)) {
            $current['category'] = $input['category'];
        }
        if (array_key_exists('dueDate', $input)) {
            $transaction->due_date = $input['dueDate'];
        }
        if (array_key_exists('paidDate', $input)) {
            $transaction->paid_date = $input['paidDate'];
        }
        if (array_key_exists('isBudget', $input)) {
            $transaction->is_budget = $input['isBudget'];
        }
        if (array_key_exists('isReserve', $input)) {
            $transaction->is_reserve = $input['isReserve'];
        }

        $this->sensitive->persistSensitive($transaction, $household, $current);
        $transaction->save();

        return $this->sensitive->formatApi($transaction->fresh()->load('items'), $household);
    }

    public function delete(Household $household, int|string $id): void
    {
        $this->ensureHousehold($household);
        $transaction = Transaction::where('household_id', $household->id)->findOrFail($id);

        $settlement = UtilitySettlement::where('household_id', $household->id)
            ->where('transaction_id', $transaction->id)
            ->first();

        if ($settlement) {
            $this->utilitySettlements->revert($settlement, $household, false);
        }

        $transaction->delete();
    }

    public function addItem(Household $household, int|string $id, array $validated): array
    {
        $this->ensureHousehold($household);
        $transaction = Transaction::where('household_id', $household->id)->with('items')->findOrFail($id);

        $current = $this->sensitive->resolve($transaction, $household);
        $items = $current['subItems'] ?? [];
        $items[] = [
            'id' => -1 * (time() % 1000000),
            'date' => $validated['date'],
            'amount' => (float) $validated['amount'],
            'reason' => $validated['reason'],
        ];
        $current['subItems'] = $items;

        $this->sensitive->persistSensitive($transaction, $household, $current);
        $transaction->save();

        return $this->sensitive->formatApi($transaction->load('items'), $household);
    }

    public function deleteItem(Household $household, int|string $txId, int|string $itemId): array
    {
        $this->ensureHousehold($household);
        $transaction = Transaction::where('household_id', $household->id)->with('items')->findOrFail($txId);

        $current = $this->sensitive->resolve($transaction, $household);
        $current['subItems'] = collect($current['subItems'] ?? [])
            ->filter(fn ($i) => (int) ($i['id'] ?? 0) !== (int) $itemId)
            ->values()
            ->all();

        $this->sensitive->persistSensitive($transaction, $household, $current);
        $transaction->save();

        if (! $transaction->encrypted_payload) {
            LedgerEntry::where('transaction_id', $transaction->id)->where('id', $itemId)->delete();
        }

        return $this->sensitive->formatApi($transaction->load('items'), $household);
    }

    public function cloneMonth(Household $household, int $userId, int $month, int $year): array
    {
        $this->ensureHousehold($household);

        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }

        $prevMonthStr = $prevYear.'-'.str_pad((string) $prevMonth, 2, '0', STR_PAD_LEFT);
        $targetMonthStr = $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $toClone = Transaction::where('household_id', $household->id)
            ->where('due_date', 'like', $prevMonthStr.'%')
            ->with('items')
            ->get();

        foreach ($toClone as $tx) {
            $sensitive = $this->sensitive->resolve($tx, $household);
            $newDate = str_replace($prevMonthStr, $targetMonthStr, $tx->due_date);

            $exists = Transaction::where('household_id', $household->id)
                ->where('due_date', $newDate)
                ->where('encrypted_payload', '!=', null)
                ->get()
                ->contains(fn ($t) => $this->sensitive->resolve($t, $household)['description'] === $sensitive['description']);

            if ($exists) {
                continue;
            }

            $clone = new Transaction([
                'household_id' => $household->id,
                'user_id' => $userId,
                'type' => $tx->type,
                'due_date' => $newDate,
                'paid_date' => null,
                'is_budget' => $tx->is_budget,
                'is_reserve' => $tx->is_reserve,
            ]);
            $this->sensitive->persistSensitive($clone, $household, $sensitive);
            $clone->save();
        }

        return ['message' => 'Hónap teljes tartalma átmásolva!'];
    }
}
