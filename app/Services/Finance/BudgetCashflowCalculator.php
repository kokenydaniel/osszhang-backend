<?php

namespace App\Services\Finance;

use App\Models\Household;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Utility;
use App\Models\Wallet;
use App\Services\EncryptedRecordService;
use App\Services\SavingService;
use App\Services\TransactionSensitiveData;
use App\Services\UtilityBalanceService;
use App\Services\WalletProvisioningService;
use Carbon\Carbon;

class BudgetCashflowCalculator
{
    public function __construct(
        private readonly TransactionSensitiveData $sensitive,
        private readonly EncryptedRecordService $crypto,
        private readonly SavingService $savings,
        private readonly WalletProvisioningService $wallets,
    ) {}

    public function compute(User $user, ?int $walletId = null, ?int $year = null, ?int $month = null): array
    {
        $household = $user->household;
        if ($household === null) {
            return $this->emptyMetrics();
        }

        $now = now();
        $year = $year ?? (int) $now->year;
        $month = $month ?? (int) $now->month;
        $monthPrefix = sprintf('%04d-%02d', $year, $month);

        $wallet = $this->resolveWallet($user, $walletId);
        $transactions = Transaction::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->with('items')
            ->get();

        $monthTransactions = $transactions->filter(
            fn (Transaction $t) => str_starts_with((string) $t->due_date, $monthPrefix),
        );

        $goalRows = $this->savings->buildGoalBudgetRowsForMonth($user, $wallet->id, $month, $year);

        $monthExpenses = $monthTransactions
            ->where('type', 'expense')
            ->where('is_reserve', false)
            ->values();

        $monthReserves = $monthTransactions
            ->where('is_reserve', true)
            ->values();

        $monthIncomes = $monthTransactions
            ->where('type', 'income')
            ->where('is_reserve', false)
            ->values();

        $monthlyBills = Utility::query()
            ->where('household_id', $household->id)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->get()
            ->filter(fn (Utility $u) => ! UtilityBalanceService::isLegacySettlementBill($u, $household, $this->crypto));

        $incomeReceived = (float) $monthIncomes
            ->filter(fn (Transaction $t) => $t->paid_date !== null)
            ->sum(fn (Transaction $t) => $this->sensitive->resolvedAmount($t, $household));

        $expensesSpent = (float) $monthExpenses->sum(
            fn (Transaction $t) => $this->sensitive->paidExpenseTotal($t, $household),
        );
        $goalSpent = (float) collect($goalRows)->sum(fn (array $row) => $this->ledgerSpentFromRow($row));
        $billsSpent = (float) $monthlyBills
            ->filter(fn (Utility $u) => $u->paid_date !== null)
            ->sum(fn (Utility $u) => $this->ourUtilityPortion($u, $household, $user));
        $spentThisMonth = $expensesSpent + $goalSpent + $billsSpent;

        $unpaidExpenses = (float) $monthExpenses
            ->filter(fn (Transaction $t) => $t->paid_date === null)
            ->sum(fn (Transaction $t) => $this->unpaidExpenseAmount($t, $household));

        $unpaidGoals = (float) collect($goalRows)
            ->filter(fn (array $row) => empty($row['paidDate']))
            ->sum(fn (array $row) => $this->unpaidExpenseFromRow($row));

        $unpaidBills = (float) $monthlyBills
            ->filter(fn (Utility $u) => $u->paid_date === null)
            ->sum(fn (Utility $u) => $this->ourUtilityPortion($u, $household, $user));

        $totalPending = round($unpaidExpenses + $unpaidGoals + $unpaidBills, 2);

        $unpaidReserves = (float) $monthReserves
            ->filter(fn (Transaction $t) => $t->paid_date === null)
            ->sum(fn (Transaction $t) => abs($this->sensitive->resolvedAmount($t, $household)));

        $today = Carbon::today()->toDateString();
        $overdueExpenses = (float) $monthExpenses
            ->filter(fn (Transaction $t) => $t->paid_date === null && (string) $t->due_date < $today)
            ->sum(fn (Transaction $t) => $this->unpaidExpenseAmount($t, $household));
        $overdueGoals = (float) collect($goalRows)
            ->filter(fn (array $row) => empty($row['paidDate']) && (string) ($row['dueDate'] ?? '') < $today)
            ->sum(fn (array $row) => $this->unpaidExpenseFromRow($row));
        $overdueBills = (float) $monthlyBills
            ->filter(fn (Utility $u) => $u->paid_date === null && (string) $u->due_date < $today)
            ->sum(fn (Utility $u) => $this->ourUtilityPortion($u, $household, $user));

        $totalBalance = (float) ($wallet->manual_balance ?? 0);
        $disposableRemaining = round($totalBalance - $totalPending - $unpaidReserves, 2);
        $monthlyBalance = round($incomeReceived - $spentThisMonth, 2);

        return [
            'wallet_id' => $wallet->id,
            'year' => $year,
            'month' => $month,
            'total_balance' => round($totalBalance, 2),
            'total_pending' => $totalPending,
            'unpaid_reserves' => round($unpaidReserves, 2),
            'disposable_remaining' => $disposableRemaining,
            'overdue_total' => round($overdueExpenses + $overdueGoals + $overdueBills, 2),
            'income_received' => round($incomeReceived, 2),
            'spent_this_month' => round($spentThisMonth, 2),
            'monthly_balance' => $monthlyBalance,
        ];
    }

    private function unpaidExpenseAmount(Transaction $transaction, Household $household): float
    {
        if ($transaction->paid_date !== null) {
            return 0.0;
        }

        $amount = $this->sensitive->resolvedAmount($transaction, $household);
        if ($transaction->is_budget) {
            $spent = $this->ledgerSpent($transaction, $household);

            return max(0.0, $amount - $spent);
        }

        return $amount;
    }

    private function unpaidExpenseFromRow(array $row): float
    {
        $amount = (float) ($row['amount'] ?? 0);
        if (! empty($row['isBudget'])) {
            return max(0.0, $amount - $this->ledgerSpentFromRow($row));
        }

        return $amount;
    }

    private function ledgerSpent(Transaction $transaction, Household $household): float
    {
        $sensitive = $this->sensitive->resolve($transaction, $household);

        return (float) collect($sensitive['subItems'] ?? [])
            ->sum(fn ($item) => abs((float) ($item['amount'] ?? 0)));
    }

    private function ledgerSpentFromRow(array $row): float
    {
        return (float) collect($row['subItems'] ?? [])
            ->sum(fn ($item) => abs((float) ($item['amount'] ?? 0)));
    }

    private function ourUtilityPortion(Utility $utility, Household $household, User $user): float
    {
        $resolved = $this->crypto->utilityResolved($utility, $household);
        $total = (float) ($resolved['total'] ?? 0);
        $splitRule = (string) ($resolved['split_rule'] ?? 'shared');

        if (! $household->utility_split_enabled) {
            return $total;
        }

        $onHouseholdSide = $household->utility_split_partner_id === null
            || (int) $user->id !== (int) $household->utility_split_partner_id;

        if ($splitRule === 'shared') {
            return $total / 2;
        }
        if ($splitRule === 'dani-private') {
            return $onHouseholdSide ? $total : 0.0;
        }
        if ($splitRule === 'ildi-private') {
            return $onHouseholdSide ? 0.0 : $total;
        }

        return 0.0;
    }

    private function resolveWallet(User $user, ?int $walletId): Wallet
    {
        if ($walletId !== null) {
            return Wallet::query()
                ->accessibleTo($user)
                ->where('household_id', $user->household_id)
                ->findOrFail($walletId);
        }

        return $this->wallets->sharedWalletForHousehold($user->household);
    }

    private function emptyMetrics(): array
    {
        return [
            'wallet_id' => 0,
            'year' => (int) now()->year,
            'month' => (int) now()->month,
            'total_balance' => 0.0,
            'total_pending' => 0.0,
            'unpaid_reserves' => 0.0,
            'disposable_remaining' => 0.0,
            'overdue_total' => 0.0,
            'income_received' => 0.0,
            'spent_this_month' => 0.0,
            'monthly_balance' => 0.0,
        ];
    }
}
