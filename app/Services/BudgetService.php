<?php

namespace App\Services;

use App\Models\Household;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UtilitySettlement;
use App\Models\Wallet;
use App\Support\MonthDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Access\AuthorizationException;

class BudgetService
{
    public function __construct(
        private readonly TransactionSensitiveData $sensitive,
        private readonly HouseholdCipherService $cipher,
        private readonly UtilitySettlementService $utilitySettlements,
        private readonly WalletProvisioningService $wallets,
        private readonly SavingService $savings,
    ) {}

    public function ensureHousehold(Household $household): Household
    {
        $this->cipher->ensureCipherKey($household);

        return $household;
    }

    /** @return list<array<string, mixed>> */
    public function listTransactionsForUser(User $user, ?int $walletId = null): array
    {
        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);

        return $this->accessibleTransactionsQuery($user, $walletId)
            ->with('items')
            ->orderBy('due_date', 'desc')
            ->get()
            ->map(fn ($t) => $this->sensitive->formatApi($t, $household))
            ->all();
    }

    /** @return list<array<string, mixed>> */
    public function goalRowsForMonth(User $user, ?int $walletId, int $month, int $year): array
    {
        $this->requireHousehold($user);

        return $this->savings->buildGoalBudgetRowsForMonth($user, $walletId, $month, $year);
    }

    /** @return array{transactions: list<array<string, mixed>>, goalRows: list<array<string, mixed>>} */
    public function listForUser(User $user, ?int $walletId = null, ?int $month = null, ?int $year = null): array
    {
        $now = now();
        $month = $month ?? (int) $now->month;
        $year = $year ?? (int) $now->year;

        return [
            'transactions' => $this->listTransactionsForUser($user, $walletId),
            'goalRows' => $this->goalRowsForMonth($user, $walletId, $month, $year),
        ];
    }

    /** @return array<string, mixed> */
    public function show(User $user, int|string $id): array
    {
        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);
        $transaction = $this->findAccessibleTransaction($user, $id);

        return $this->sensitive->formatApi($transaction, $household);
    }

    /** @return array<string, mixed> */
    public function create(User $user, array $validated): array
    {
        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);

        $wallet = $this->resolveWalletForMutation(
            $user,
            isset($validated['walletId']) ? (int) $validated['walletId'] : null,
        );

        $transaction = new Transaction([
            'household_id' => $household->id,
            'wallet_id' => $wallet->id,
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

    /** @return array<string, mixed> */
    public function update(User $user, int|string $id, array $input): array
    {
        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);
        $transaction = $this->findAccessibleTransaction($user, $id);

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
        if (array_key_exists('walletId', $input) && $input['walletId'] !== null) {
            $wallet = $this->resolveWalletForMutation($user, (int) $input['walletId']);
            $transaction->wallet_id = $wallet->id;
        }

        $this->sensitive->persistSensitive($transaction, $household, $current);
        $transaction->save();

        return $this->sensitive->formatApi($transaction->fresh()->load('items'), $household);
    }

    public function delete(User $user, int|string $id): void
    {
        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);
        $transaction = $this->findAccessibleTransaction($user, $id);

        $settlement = UtilitySettlement::where('household_id', $household->id)
            ->where('transaction_id', $transaction->id)
            ->first();

        if ($settlement) {
            $this->utilitySettlements->revert($settlement, $household, false);
        }

        $transaction->delete();
    }

    /** @return array<string, mixed> */
    public function addItem(User $user, int|string $id, array $validated): array
    {
        $savingId = SavingService::parseGoalVirtualId($id);
        if ($savingId !== null) {
            return $this->savings->addGoalBudgetEntry($user, $savingId, $validated);
        }

        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);
        $transaction = $this->findAccessibleTransaction($user, $id);

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

    /** @return array<string, mixed> */
    public function updateItem(User $user, int|string $txId, int|string $itemId, array $validated): array
    {
        if (SavingService::parseGoalVirtualId($txId) !== null) {
            abort(404);
        }

        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);
        $transaction = $this->findAccessibleTransaction($user, $txId);

        $current = $this->sensitive->resolve($transaction, $household);
        $current['subItems'] = collect($current['subItems'] ?? [])
            ->map(function (array $item) use ($itemId, $validated) {
                if ((int) ($item['id'] ?? 0) !== (int) $itemId) {
                    return $item;
                }

                if (array_key_exists('amount', $validated)) {
                    $item['amount'] = (float) $validated['amount'];
                }
                if (array_key_exists('reason', $validated)) {
                    $item['reason'] = $validated['reason'];
                }
                if (array_key_exists('date', $validated)) {
                    $item['date'] = $validated['date'];
                }

                return $item;
            })
            ->values()
            ->all();

        $this->sensitive->persistSensitive($transaction, $household, $current);
        $transaction->save();

        return $this->sensitive->formatApi($transaction->load('items'), $household);
    }

    /** @return array<string, mixed> */
    public function deleteItem(User $user, int|string $txId, int|string $itemId): array
    {
        $savingId = SavingService::parseGoalVirtualId($txId);
        if ($savingId !== null) {
            return $this->savings->deleteGoalBudgetEntry($user, $savingId, (int) $itemId);
        }

        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);
        $transaction = $this->findAccessibleTransaction($user, $txId);

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

    /** @return array<string, mixed> */
    public function upsertGoalMonthlyActual(User $user, int $savingId, int $month, int $year, float $amount, ?string $reason = null): array
    {
        return $this->savings->upsertMonthlyActual($user, $savingId, $month, $year, $amount, $reason);
    }

    /** @return array{message: string} */
    public function cloneMonth(User $user, int $month, int $year, ?int $walletId = null): array
    {
        $household = $this->requireHousehold($user);
        $this->ensureHousehold($household);

        $targetWallet = $this->resolveWalletForMutation($user, $walletId);

        $prevMonth = $month - 1;
        $prevYear = $year;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }

        $prevMonthStr = $prevYear.'-'.str_pad((string) $prevMonth, 2, '0', STR_PAD_LEFT);
        $targetMonthStr = $year.'-'.str_pad((string) $month, 2, '0', STR_PAD_LEFT);

        $cloneMode = $household->resolvedBudgetSettings()['clone_mode'] ?? 'all';

        $toClone = $this->accessibleTransactionsQuery($user, $targetWallet->id)
            ->where('due_date', 'like', $prevMonthStr.'%')
            ->with('items')
            ->get()
            ->filter(function (Transaction $tx) use ($cloneMode) {
                return match ($cloneMode) {
                    'budget_only' => (bool) $tx->is_budget,
                    'fixed_recurring' => (bool) $tx->is_budget && ! $tx->is_reserve,
                    default => true,
                };
            });

        foreach ($toClone as $tx) {
            $sensitive = $this->sensitive->resolve($tx, $household);
            $newDate = MonthDates::shiftToMonth($tx->due_date, $year, $month);

            $exists = $this->accessibleTransactionsQuery($user, $targetWallet->id)
                ->where('due_date', $newDate)
                ->where('encrypted_payload', '!=', null)
                ->get()
                ->contains(fn ($t) => $this->sensitive->resolve($t, $household)['description'] === $sensitive['description']);

            if ($exists) {
                continue;
            }

            $clone = new Transaction([
                'household_id' => $household->id,
                'wallet_id' => $targetWallet->id,
                'user_id' => $user->id,
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

    private function requireHousehold(User $user): Household
    {
        if ($user->household === null) {
            throw new AuthorizationException('Nincs háztartás a felhasználóhoz rendelve.');
        }

        return $user->household;
    }

    /** @return Builder<Transaction> */
    private function accessibleTransactionsQuery(User $user, ?int $walletId = null): Builder
    {
        $query = Transaction::query()->accessibleTo($user);

        if ($walletId !== null) {
            $query->where('wallet_id', $walletId);
        }

        return $query;
    }

    private function findAccessibleTransaction(User $user, int|string $id): Transaction
    {
        return $this->accessibleTransactionsQuery($user)
            ->with('items')
            ->findOrFail($id);
    }

    private function resolveWalletForMutation(User $user, ?int $walletId): Wallet
    {
        if ($walletId !== null) {
            return Wallet::query()
                ->accessibleTo($user)
                ->where('household_id', $user->household_id)
                ->findOrFail($walletId);
        }

        return $this->wallets->ensureSharedWallet($this->requireHousehold($user));
    }
}
