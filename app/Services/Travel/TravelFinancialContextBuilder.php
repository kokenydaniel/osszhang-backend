<?php

namespace App\Services\Travel;

use App\Models\Debt;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\EncryptedRecordService;
use App\Services\Finance\BudgetCashflowCalculator;
use App\Services\Finance\TravelEligibleSavingsCalculator;
use App\Services\SavingService;
use App\Services\TransactionSensitiveData;
use App\Services\WalletProvisioningService;
use Carbon\Carbon;

class TravelFinancialContextBuilder
{
    public function __construct(
        private readonly TransactionSensitiveData $sensitive,
        private readonly EncryptedRecordService $crypto,
        private readonly WalletProvisioningService $wallets,
        private readonly SavingService $savings,
        private readonly BudgetCashflowCalculator $cashflow,
        private readonly TravelEligibleSavingsCalculator $travelSavings,
    ) {}

    /**
     * @param  array<string, float|int|string>|null  $exchangeRates
     * @return array<string, mixed>
     */
    public function build(User $user, ?int $walletId, ?string $targetDate, ?array $exchangeRates = null): array
    {
        $household = $user->household;
        if ($household === null) {
            return [];
        }

        $wallet = $this->resolveWallet($user, $walletId);
        $now = now();
        $cashflowMetrics = $this->cashflow->compute($user, $wallet->id, (int) $now->year, (int) $now->month);
        $eligibleSavings = $this->travelSavings->compute($user, $wallet, $household, $exchangeRates);
        $availableForTrip = round(
            (float) $cashflowMetrics['disposable_remaining'] + (float) $eligibleSavings['total_huf'],
            2,
        );

        $transactions = Transaction::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->with('items')
            ->get();

        $lockedSavings = (float) Saving::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->where('count_in_savings', true)
            ->get()
            ->sum(function (Saving $s) use ($household) {
                $formatted = $this->crypto->formatSaving($s, $household);

                return (float) ($formatted['current_amount'] ?? 0);
            });

        $savingsGoals = Saving::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->where('type', Saving::TYPE_GOAL)
            ->with('ledger')
            ->get()
            ->map(function (Saving $s) use ($household) {
                $formatted = $this->crypto->formatSaving($s, $household);
                $current = (float) ($formatted['current_amount'] ?? 0);
                $target = (float) ($formatted['goal_amount'] ?? 0);

                return [
                    'title' => (string) ($formatted['institution'] ?? ''),
                    'target_amount' => $target,
                    'current_amount' => $current,
                    'remaining_amount' => max(0, $target - $current),
                    'target_date' => $formatted['target_date'] ?? null,
                ];
            })
            ->values()
            ->all();

        $totalDebts = (float) Debt::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->get()
            ->sum(function ($d) use ($household) {
                $s = $this->crypto->debtResolved($d, $household);

                return max(0, (float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0));
            });

        $monthsUntilTrip = null;
        $requiredMonthlySavings = null;
        if ($targetDate) {
            $target = Carbon::parse($targetDate)->startOfDay();
            $monthsUntilTrip = max(1, (int) $now->diffInMonths($target, false));
        }

        $monthlySurplusDetails = $this->estimateMonthlySurplusDetails($transactions, $household, 3);
        $monthlySavingsCapacity = round(max(0, $monthlySurplusDetails['average_huf']), 2);

        return [
            'wallet_id' => $wallet->id,
            'year' => (int) $cashflowMetrics['year'],
            'month' => (int) $cashflowMetrics['month'],
            'total_balance' => (float) $cashflowMetrics['total_balance'],
            'total_pending' => (float) $cashflowMetrics['total_pending'],
            'unpaid_reserves' => (float) $cashflowMetrics['unpaid_reserves'],
            'disposable_remaining' => (float) $cashflowMetrics['disposable_remaining'],
            'travel_eligible_savings_huf' => (float) $eligibleSavings['total_huf'],
            'travel_eligible_savings_items' => $eligibleSavings['items'],
            'count_in_savings_total_huf' => (float) $eligibleSavings['count_in_savings_total_huf'],
            'travel_excluded_savings_items' => $eligibleSavings['excluded_items'],
            'travel_excluded_savings_huf' => (float) $eligibleSavings['excluded_total_huf'],
            'exchange_rates_huf_per_unit' => $eligibleSavings['exchange_rates_huf_per_unit'],
            'available_for_trip_huf' => $availableForTrip,
            'monthly_balance' => (float) $cashflowMetrics['monthly_balance'],
            'income_received' => (float) $cashflowMetrics['income_received'],
            'spent_this_month' => (float) $cashflowMetrics['spent_this_month'],
            'overdue_total' => (float) $cashflowMetrics['overdue_total'],
            'locked_savings' => round($lockedSavings, 2),
            'total_debts' => round($totalDebts, 2),
            'savings_goals' => $savingsGoals,
            'months_until_trip' => $monthsUntilTrip,
            'monthly_savings_capacity_huf' => $monthlySavingsCapacity,
            'monthly_surplus_breakdown' => $monthlySurplusDetails['months'],
            'required_monthly_savings_huf' => $requiredMonthlySavings,
        ];
    }

    /**
     * @param  array<string, mixed>  $financialContext
     * @return array<string, mixed>
     */
    public function buildSavingsPlan(array $financialContext, float $tripCost, ?string $targetDate): array
    {
        $disposable = round((float) ($financialContext['disposable_remaining'] ?? 0), 2);
        $travelSavings = round((float) ($financialContext['travel_eligible_savings_huf'] ?? 0), 2);
        $travelSavingsItems = $financialContext['travel_eligible_savings_items'] ?? [];
        $countInSavingsTotal = round((float) ($financialContext['count_in_savings_total_huf'] ?? 0), 2);
        $excludedSavingsItems = $financialContext['travel_excluded_savings_items'] ?? [];
        $excludedSavingsTotal = round((float) ($financialContext['travel_excluded_savings_huf'] ?? 0), 2);
        $monthlySurplusBreakdown = $financialContext['monthly_surplus_breakdown'] ?? [];
        $exchangeRatesUsed = $financialContext['exchange_rates_huf_per_unit'] ?? [];
        $available = round((float) ($financialContext['available_for_trip_huf'] ?? ($disposable + $travelSavings)), 2);
        $capacity = round((float) ($financialContext['monthly_savings_capacity_huf'] ?? 0), 2);
        $monthsUntilTrip = $financialContext['months_until_trip'] ?? null;
        $canPayNow = $available >= $tripCost;
        $shortfall = round(max(0, $tripCost - $available), 2);

        $displayFields = [
            'trip_cost_huf' => round($tripCost, 2),
            'disposable_remaining_huf' => $disposable,
            'travel_eligible_savings_huf' => $travelSavings,
            'travel_eligible_savings_items' => $travelSavingsItems,
            'count_in_savings_total_huf' => $countInSavingsTotal,
            'travel_excluded_savings_items' => $excludedSavingsItems,
            'travel_excluded_savings_huf' => $excludedSavingsTotal,
            'exchange_rates_huf_per_unit' => $exchangeRatesUsed,
            'available_for_trip_huf' => $available,
            'monthly_surplus_breakdown' => $monthlySurplusBreakdown,
        ];

        if ($targetDate === null || $monthsUntilTrip === null) {
            $summary = $canPayNow
                ? sprintf(
                    'Az utazás becsült költsége %s. Rendelkezésre áll: Marad %s%s — ez fedezi az utat.',
                    $this->fmt($tripCost),
                    $this->fmt($disposable),
                    $travelSavings > 0
                        ? sprintf(' + utazásra számítható megtakarítás %s = %s', $this->fmt($travelSavings), $this->fmt($available))
                        : '',
                )
                : sprintf(
                    'Az utazás %s, rendelkezésre áll %s (Marad %s%s). Hiány: %s. Add meg az indulás dátumát a havi félretételi tervhez.',
                    $this->fmt($tripCost),
                    $this->fmt($available),
                    $this->fmt($disposable),
                    $travelSavings > 0 ? sprintf(' + megtakarítás %s', $this->fmt($travelSavings)) : '',
                    $this->fmt($shortfall),
                );

            return array_merge($displayFields, [
                'monthly_amount_huf' => null,
                'months' => null,
                'target_date' => null,
                'fits_current_budget' => $canPayNow,
                'can_pay_now' => $canPayNow,
                'has_savings_schedule' => false,
                'summary' => $summary,
                'monthly_savings_capacity_huf' => $capacity,
                'required_monthly_savings_huf' => null,
            ]);
        }

        $months = max(1, (int) $monthsUntilTrip);
        $requiredMonthly = round($shortfall / $months, 2);
        $fitsMonthly = $shortfall <= 0 || ($capacity > 0 && $capacity >= $requiredMonthly);
        $fits = $canPayNow || $fitsMonthly;

        if ($canPayNow) {
            $summary = sprintf(
                'Az utazás %s fedezhető a rendelkezésre álló %s összegből (Marad %s%s).',
                $this->fmt($tripCost),
                $this->fmt($available),
                $this->fmt($disposable),
                $travelSavings > 0 ? sprintf(' + megtakarítás %s', $this->fmt($travelSavings)) : '',
            );
        } elseif ($fitsMonthly) {
            $summary = sprintf(
                'Most %s áll rendelkezésre (Marad %s%s). A hiány (%s) fedezhető havi %s félretétellel %d hónap alatt.',
                $this->fmt($available),
                $this->fmt($disposable),
                $travelSavings > 0 ? sprintf(' + megtakarítás %s', $this->fmt($travelSavings)) : '',
                $this->fmt($shortfall),
                $this->fmt($requiredMonthly),
                $months,
            );
        } else {
            $summary = sprintf(
                'Az utazás %s, rendelkezésre áll %s (Marad %s%s). Hiány %s — havi %s kellene %d hónap alatt, de a becsült havi kapacitás csak %s (az elmúlt 3 hónap átlagos többlete: bevétel − kifizetett kiadás).',
                $this->fmt($tripCost),
                $this->fmt($available),
                $this->fmt($disposable),
                $travelSavings > 0 ? sprintf(' + megtakarítás %s', $this->fmt($travelSavings)) : '',
                $this->fmt($shortfall),
                $this->fmt($requiredMonthly),
                $months,
                $this->fmt($capacity),
            );
        }

        return array_merge($displayFields, [
            'monthly_amount_huf' => $requiredMonthly,
            'months' => $months,
            'target_date' => $targetDate,
            'fits_current_budget' => $fits,
            'can_pay_now' => $canPayNow,
            'fits_monthly_savings' => $fitsMonthly,
            'has_savings_schedule' => true,
            'summary' => $summary,
            'monthly_savings_capacity_huf' => $capacity,
            'required_monthly_savings_huf' => $requiredMonthly,
        ]);
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

    /**
     * @param  \Illuminate\Support\Collection<int, Transaction>  $transactions
     * @return array{months: list<array{label: string, income_huf: float, expense_huf: float, surplus_huf: float}>, average_huf: float}
     */
    private function estimateMonthlySurplusDetails($transactions, $household, int $monthsBack): array
    {
        $now = now();
        $months = [];
        $surpluses = [];

        for ($i = 0; $i < $monthsBack; $i++) {
            $date = $now->copy()->subMonths($i);
            $prefix = $date->format('Y-m');
            $monthRows = $transactions->filter(fn ($t) => str_starts_with((string) $t->due_date, $prefix));
            $income = (float) $monthRows
                ->where('type', 'income')
                ->where('is_reserve', false)
                ->sum(fn ($t) => $this->sensitive->resolvedAmount($t, $household));
            $expense = (float) $monthRows
                ->where('type', 'expense')
                ->where('is_reserve', false)
                ->sum(fn ($t) => $this->sensitive->paidExpenseTotal($t, $household));
            $surplus = $income - $expense;
            $surpluses[] = $surplus;
            $months[] = [
                'label' => $date->format('Y. m.'),
                'income_huf' => round($income, 2),
                'expense_huf' => round($expense, 2),
                'surplus_huf' => round($surplus, 2),
            ];
        }

        $average = $surpluses === [] ? 0.0 : round(array_sum($surpluses) / count($surpluses), 2);

        return [
            'months' => array_reverse($months),
            'average_huf' => $average,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Transaction>  $transactions
     */
    private function estimateMonthlySurplus($transactions, $household, int $monthsBack): float
    {
        return $this->estimateMonthlySurplusDetails($transactions, $household, $monthsBack)['average_huf'];
    }

    private function fmt(float $amount): string
    {
        return number_format($amount, 0, ',', ' ').' Ft';
    }
}
