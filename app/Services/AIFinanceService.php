<?php

namespace App\Services;

use App\Models\BusinessOrder;
use App\Models\Debt;
use App\Models\Household;
use App\Models\Meter;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Utility;
use App\Models\Wallet;
use App\Services\WalletProvisioningService;
use Carbon\Carbon;

class AIFinanceService
{
    public function __construct(
        private OpenAIService $ai,
        private TransactionSensitiveData $sensitive,
        private HouseholdCipherService $cipher,
        private EncryptedRecordService $crypto,
        private WalletProvisioningService $wallets,
    ) {}

    public function ensureHousehold(Household $household): Household
    {
        $this->cipher->ensureCipherKey($household);

        return $household;
    }

    public function envelope(array $data, bool $fallbackUsed = false, ?string $failureReason = null): array
    {
        return [
            'data' => $data,
            'meta' => [
                'mode' => 'strict_ai',
                'provider' => 'openai',
                'fallback_used' => $fallbackUsed,
                'failure_reason' => $failureReason,
                'generated_at' => now()->toIso8601String(),
            ],
        ];
    }

    public function autoCategorizeTransaction(array $validated): array
    {
        $fallback = function () use ($validated) {
            $description = mb_strtolower($validated['description']);
            $categories = $validated['candidate_categories'];
            $picked = $categories[0];

            $keywords = [
                'Rezsi' => ['eon', 'mvm', 'villany', 'gáz', 'viz', 'víz', 'csatorna', 'rezsi'],
                'Élelmiszer' => ['aldi', 'lidl', 'spar', 'tesco', 'auchan', 'kaja', 'etel', 'étel', 'food', 'élelmiszer'],
                'Kaja' => ['aldi', 'lidl', 'spar', 'tesco', 'auchan', 'kaja', 'etel', 'étel', 'food'],
                'Tankolás' => ['mol', 'omv', 'shell', 'tank'],
                'Autó' => ['szerviz', 'gumi', 'parkol', 'auto', 'autó'],
                'Streaming, Subscriptions' => ['spotify', 'netflix', 'youtube', 'apple', 'subscription'],
            ];

            foreach ($keywords as $category => $words) {
                if (! in_array($category, $categories, true)) {
                    continue;
                }
                foreach ($words as $word) {
                    if (str_contains($description, $word)) {
                        $picked = $category;
                        break 2;
                    }
                }
            }

            return [
                'category' => $picked,
                'confidence' => 0.55,
                'normalized_description' => trim($validated['description']),
                'rationale' => 'Szabályalapú fallback besorolás kulcsszavak alapján.',
            ];
        };

        try {
            $prompt = "Kategorizáld ezt a tranzakciót a megadott kategóriák egyikébe, és adj vissza KIZÁRÓLAG érvényes JSON-t ilyen mezőkkel: category (string), confidence (0..1 number), normalized_description (string), rationale (string).\n".
                "Leírás: {$validated['description']}\n".
                'Típus: '.($validated['type'] ?? 'ismeretlen')."\n".
                'Összeg: '.($validated['amount'] ?? 'ismeretlen')."\n".
                'Engedélyezett kategóriák: '.implode(', ', $validated['candidate_categories'])."\n".
                'A category mező csak a felsorolt kategóriák egyike lehet.';

            $decoded = $this->ai->askJson(
                $prompt,
                'Te egy adatfeldolgozó vagy. Adj vissza KIZÁRÓLAG érvényes JSON-t a kért mezőkkel.',
            );
            if (! isset($decoded['category']) || ! in_array($decoded['category'], $validated['candidate_categories'], true)) {
                throw new \RuntimeException('Invalid category from model');
            }
            $result = [
                'category' => $decoded['category'],
                'confidence' => max(0, min(1, (float) ($decoded['confidence'] ?? 0.6))),
                'normalized_description' => (string) ($decoded['normalized_description'] ?? trim($validated['description'])),
                'rationale' => (string) ($decoded['rationale'] ?? 'AI kategorizálás.'),
            ];

            return $this->envelope($result);
        } catch (\Throwable $e) {
            return $this->envelope($fallback(), true, $e->getMessage());
        }
    }

    public function overspendRootCause(Household $household, User $user, array $validated): array
    {
        $this->ensureHousehold($household);
        $wallet = $this->resolveWallet($user, $validated['wallet_id'] ?? null);
        $monthPrefix = sprintf('%04d-%02d', $validated['year'], $validated['month']);

        $transactions = Transaction::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->with('items')
            ->get();

        $utilities = Utility::where('household_id', $household->id)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->get();

        $incomeReceived = $transactions
            ->where('type', 'income')
            ->where('is_reserve', false)
            ->whereNotNull('paid_date')
            ->sum(fn ($t) => $this->sensitive->resolvedAmount($t, $household));

        $expensePaid = $transactions
            ->where('type', 'expense')
            ->where('is_reserve', false)
            ->sum(fn ($t) => $this->sensitive->paidExpenseTotal($t, $household));

        $utilityPaid = $wallet->is_shared
            ? (float) $utilities->whereNotNull('paid_date')->sum(
                fn ($u) => $this->utilityHouseholdPortion($u, $household),
            )
            : 0.0;
        $monthlyBalance = (float) $incomeReceived - (float) $expensePaid - (float) $utilityPaid;
        $overspendAmount = $monthlyBalance < 0 ? abs($monthlyBalance) : 0.0;

        $categoryTotals = $transactions->where('type', 'expense')->where('is_reserve', false)
            ->groupBy(fn ($t) => $this->sensitive->resolvedCategory($t, $household))
            ->map(fn ($rows) => $rows->sum(fn ($t) => $this->sensitive->expenseTotal($t, $household)));
        $topDrivers = collect($categoryTotals)
            ->sortDesc()
            ->take(3)
            ->map(fn ($amount, $category) => ['category' => $category, 'amount' => $amount])
            ->values()
            ->all();

        $fallbackPayload = [
            'status' => $overspendAmount > 0 ? 'overspent' : 'ok',
            'overspend_amount' => $overspendAmount,
            'top_drivers' => $topDrivers,
            'actions' => $overspendAmount > 0
                ? ['Fagyaszd a legnagyobb kiadási kategóriát 7 napra.', 'Nézd át a függő tételeket és halassz 1-2 alacsony prioritásút.']
                : ['A havi kereted kontroll alatt van, tartsd az aktuális tempót.'],
            'confidence' => 0.6,
        ];

        try {
            $prompt = "Adj vissza KIZÁRÓLAG JSON-t: status (overspent|ok), overspend_amount (number), top_drivers (array of {category,amount}), actions (max 3 rövid magyar teendő), confidence (0..1).\n".
                "Hónap: {$monthPrefix}\n".
                "Havi egyenleg: {$monthlyBalance}\n".
                'Top kategóriák: '.json_encode($topDrivers);
            $decoded = $this->ai->askJson(
                $prompt,
                'Te egy pénzügyi elemző vagy. Adj vissza KIZÁRÓLAG érvényes JSON-t a kért mezőkkel.',
            );

            return $this->envelope($decoded);
        } catch (\Throwable $e) {
            return $this->envelope($fallbackPayload, true, $e->getMessage());
        }
    }

    public function cashflowForecast(Household $household, User $user, array $validated): array
    {
        $this->ensureHousehold($household);
        $wallet = $this->resolveWallet($user, $validated['wallet_id'] ?? null);
        $monthPrefix = sprintf('%04d-%02d', $validated['year'], $validated['month']);

        $transactions = Transaction::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->with('items')
            ->get();

        $receivedIncome = (float) $transactions
            ->where('type', 'income')
            ->where('is_reserve', false)
            ->whereNotNull('paid_date')
            ->sum(fn ($t) => $this->sensitive->resolvedAmount($t, $household));
        $pendingIncome = (float) $transactions
            ->where('type', 'income')
            ->where('is_reserve', false)
            ->whereNull('paid_date')
            ->sum(fn ($t) => $this->sensitive->resolvedAmount($t, $household));

        $paidExpense = (float) $transactions
            ->where('type', 'expense')
            ->where('is_reserve', false)
            ->sum(fn ($t) => $this->sensitive->paidExpenseTotal($t, $household));
        $pendingExpense = (float) $transactions
            ->where('type', 'expense')
            ->where('is_reserve', false)
            ->whereNull('paid_date')
            ->filter(fn ($t) => ! $t->is_budget)
            ->sum(fn ($t) => $this->sensitive->resolvedAmount($t, $household));

        $pendingUtility = 0.0;
        if ($wallet->is_shared) {
            $utilities = Utility::where('household_id', $household->id)
                ->where('due_date', 'like', $monthPrefix.'%')
                ->get();
            $pendingUtility = (float) $utilities->whereNull('paid_date')->sum(
                fn ($u) => $this->utilityHouseholdPortion($u, $household),
            );
        }

        $walletDebts = Debt::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->get();
        $debtMinimumPayments = (float) $walletDebts->sum(function ($d) use ($household) {
            $s = $this->crypto->debtResolved($d, $household);
            $remaining = (float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0);

            return $remaining > 0 ? (float) ($s['minimum_payment'] ?? 0) : 0.0;
        });

        $p50 = $receivedIncome + ($pendingIncome * 0.8) - $paidExpense - ($pendingExpense * 0.85) - ($pendingUtility * 0.85) - $debtMinimumPayments;
        $payload = [
            'forecast_balance' => round($p50, 2),
            'p10' => round($p50 - max(30000, abs($p50) * 0.2), 2),
            'p50' => round($p50, 2),
            'p90' => round($p50 + max(30000, abs($p50) * 0.2), 2),
            'assumptions' => [
                'pending_income_probability' => 0.8,
                'pending_expense_probability' => 0.85,
                'pending_utility_probability' => 0.85,
                'debt_minimum_payments' => round($debtMinimumPayments, 2),
            ],
            'risk_flags' => array_values(array_filter([
                $p50 < 0 ? 'Hónap végi negatív egyenleg kockázat.' : null,
                $debtMinimumPayments > 0 && $p50 < $debtMinimumPayments * 2
                    ? 'A kasszához tartozó hiteltörlesztések jelentősen terhelhetik a havi egyenleget.'
                    : null,
            ])),
        ];

        return $this->envelope($payload);
    }

    public function utilityAnomalies(Household $household, array $validated): array
    {
        $this->ensureHousehold($household);
        $meters = Meter::where('household_id', $household->id)->with('readings')->get();
        $anomalies = [];
        foreach ($meters as $meter) {
            $meterData = $this->crypto->meterResolved($meter, $household);
            $target = $meter->readings->first(fn ($r) => (int) $r->year === (int) $validated['year'] && (int) $r->month === (int) $validated['month']);
            if (! $target) {
                continue;
            }
            $targetData = $this->crypto->readingResolved($target, $household);
            $historical = $meter->readings
                ->filter(fn ($r) => ! ((int) $r->year === (int) $validated['year'] && (int) $r->month === (int) $validated['month']))
                ->map(fn ($r) => max(0, (float) ($this->crypto->readingResolved($r, $household)['consumption'] ?? 0)))
                ->values();

            if ($historical->count() < 3) {
                continue;
            }
            $avg = $historical->avg();
            $threshold = max(1, $avg * 0.35);
            $actualConsumption = (float) ($targetData['consumption'] ?? 0);
            $diff = $actualConsumption - (float) $avg;
            if (abs($diff) > $threshold) {
                $anomalies[] = [
                    'meter_id' => $meter->id,
                    'meter_name' => (string) ($meterData['name'] ?? ''),
                    'expected' => round($avg, 2),
                    'actual' => $actualConsumption,
                    'severity' => abs($diff) > ($avg * 0.6) ? 'high' : 'medium',
                    'reason' => $diff > 0 ? 'Szokatlanul magas fogyasztás az átlaghoz képest.' : 'Szokatlanul alacsony fogyasztás az átlaghoz képest.',
                ];
            }
        }

        return $this->envelope([
            'anomalies' => $anomalies,
            'recommendations' => count($anomalies)
                ? ['Ellenőrizd az érintett mérőkhöz tartozó utolsó leolvasást és reset jelöléseket.']
                : ['Nem látható jelentős rezsi anomália a kiválasztott hónapban.'],
        ]);
    }

    public function savingsRecommendations(Household $household, User $user, array $validated): array
    {
        $this->ensureHousehold($household);
        $wallet = $this->resolveWallet($user, $validated['wallet_id'] ?? null);
        $savings = Saving::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->with('ledger')
            ->get();
        $currentTotal = (float) $savings->sum(function ($s) use ($household) {
            return $s->ledger->sum(fn ($e) => (float) ($this->crypto->ledgerResolved($e, $household)['amount'] ?? 0));
        });
        $goals = collect($validated['goals']);
        $totalTarget = (float) $goals->sum('target_amount');
        $gap = max(0, $totalTarget - $currentTotal);

        $minMonths = max(1, $goals->map(function ($goal) {
            return now()->diffInMonths(Carbon::parse($goal['target_date']), false);
        })->filter(fn ($m) => $m > 0)->min() ?? 12);
        $monthlyNeed = $gap > 0 ? round($gap / $minMonths, 2) : 0;

        $plan = $goals->map(function ($goal) use ($totalTarget, $monthlyNeed) {
            $share = $totalTarget > 0 ? ((float) $goal['target_amount'] / $totalTarget) : 0;

            return [
                'goal' => $goal['name'],
                'monthly_allocation' => round($monthlyNeed * $share, 2),
                'target_date' => $goal['target_date'],
            ];
        })->values()->all();

        return $this->envelope([
            'monthly_allocation_plan' => $plan,
            'projected_completion' => $plan,
            'tradeoffs' => $gap > 0
                ? ['Ha magasabb havi félrerakást vállalsz, gyorsabban teljesül a célcsomag.']
                : ['A jelenlegi széf állomány fedezi a megadott célokat.'],
        ]);
    }

    public function optimizeDebts(Household $household, User $user, string $strategy, ?int $walletId = null): array
    {
        $this->ensureHousehold($household);
        $wallet = $this->resolveWallet($user, $walletId);
        $debts = Debt::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->get();
        $active = $debts->filter(function ($d) use ($household) {
            $s = $this->crypto->debtResolved($d, $household);

            return ((float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0)) > 0;
        })->values();

        $ordered = $active->sortBy(function ($debt) use ($strategy, $household) {
            $s = $this->crypto->debtResolved($debt, $household);
            $remaining = (float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0);
            if ($strategy === 'snowball') {
                return $remaining;
            }

            return -1 * (float) ($s['annual_interest_rate'] ?? 0);
        })->values();

        $schedule = $ordered->map(function ($debt, $index) use ($household) {
            $s = $this->crypto->debtResolved($debt, $household);
            $remaining = (float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0);

            return [
                'rank' => $index + 1,
                'debt_id' => $debt->id,
                'name' => (string) ($s['name'] ?? ''),
                'remaining' => round($remaining, 2),
                'recommended_extra_payment' => round(max((float) ($s['minimum_payment'] ?? 0), $remaining * 0.08), 2),
            ];
        })->all();

        return $this->envelope([
            'strategy' => in_array($strategy, ['avalanche', 'snowball'], true) ? $strategy : 'avalanche',
            'schedule' => $schedule,
            'payoff_date' => now()->addMonths(max(1, count($schedule) * 3))->toDateString(),
            'total_interest' => round($active->sum(function ($d) use ($household) {
                $s = $this->crypto->debtResolved($d, $household);
                $remaining = (float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0);

                return $remaining * ((float) ($s['annual_interest_rate'] ?? 0) / 100) * 0.5;
            }), 2),
            'alternatives' => ['avalanche', 'snowball'],
        ]);
    }

    public function weeklyBriefing(Household $household, User $user, ?string $weekStart, ?int $walletId = null): array
    {
        $this->ensureHousehold($household);
        $wallet = $this->resolveWallet($user, $walletId);
        $weekStartDate = $weekStart
            ? Carbon::parse($weekStart)->startOfDay()
            : now()->startOfWeek();
        $weekEnd = $weekStartDate->copy()->endOfWeek();

        $transactions = Transaction::query()
            ->accessibleTo($user)
            ->where('wallet_id', $wallet->id)
            ->whereBetween('due_date', [$weekStartDate->toDateString(), $weekEnd->toDateString()])
            ->with('items')
            ->get();
        $income = (float) $transactions
            ->where('type', 'income')
            ->sum(fn ($t) => $this->sensitive->resolvedAmount($t, $household));
        $expense = (float) $transactions
            ->where('type', 'expense')
            ->sum(fn ($t) => $this->sensitive->expenseTotal($t, $household));
        $balance = $income - $expense;

        $payload = [
            'headline' => $balance >= 0 ? 'A heti pénzáram jelenleg stabil.' : 'A heti költés meghaladta a bevételeket.',
            'wins' => $balance >= 0 ? ['A heti egyenleg pozitív.'] : [],
            'risks' => $balance < 0 ? ['A heti egyenleg negatív, rövid távú keretszűkítés javasolt.'] : [],
            'next_actions' => [
                'Nézd át a következő 7 nap függő tranzakcióit.',
                'A legnagyobb kiadási kategóriára állíts plafont a hét végéig.',
            ],
            'week_kpis' => [
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'balance' => round($balance, 2),
            ],
        ];

        return $this->envelope($payload);
    }

    public function query(User $user, string $prompt, bool $includeContext, ?int $walletId = null): array
    {
        $context = [];
        if ($includeContext) {
            $household = $user->household;
            $wallet = $this->resolveWallet($user, $walletId);

            $transactions = Transaction::query()
                ->accessibleTo($user)
                ->where('wallet_id', $wallet->id)
                ->with('items')
                ->get();

            if ($household) {
                $this->cipher->ensureCipherKey($household);
            }
            $income = (float) $transactions
                ->where('type', 'income')
                ->sum(fn ($t) => $this->sensitive->resolvedAmount($t, $household));
            $expense = (float) $transactions
                ->where('type', 'expense')
                ->sum(fn ($t) => $this->sensitive->expenseTotal($t, $household));

            $unpaidUtilities = 0.0;
            $savingsTotal = 0.0;
            $debtsTotal = 0.0;
            $ordersTotal = 0.0;
            $pendingOrdersCount = 0;

            if ($wallet->is_shared && $household) {
                $utilities = Utility::where('household_id', $user->household_id)->get();
                $unpaidUtilities = (float) $utilities->whereNull('paid_date')->sum(
                    fn ($u) => $this->utilityHouseholdPortion($u, $household),
                );

                $orders = BusinessOrder::where('household_id', $user->household_id)->get();
                $ordersTotal = (float) $orders->sum(fn ($o) => (float) ($this->crypto->businessOrderResolved($o, $household)['amount'] ?? 0));
                $pendingOrdersCount = $orders->where('state', '!=', 'RENDBEN')->count();
            }

            $savings = Saving::query()
                ->accessibleTo($user)
                ->where('wallet_id', $wallet->id)
                ->with('ledger')
                ->get();
            $savingsTotal = (float) $savings->sum(function ($s) use ($household) {
                return $s->ledger->sum(fn ($e) => (float) ($this->crypto->ledgerResolved($e, $household)['amount'] ?? 0));
            });

            $debts = Debt::query()
                ->accessibleTo($user)
                ->where('wallet_id', $wallet->id)
                ->get();
            $debtsTotal = (float) $debts->sum(function ($d) use ($household) {
                $s = $this->crypto->debtResolved($d, $household);

                return (float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0);
            });

            $context = [
                'user_name' => $user->first_name.' '.$user->last_name,
                'household' => $household ? $household->name : 'Nincs háztartás',
                'wallet' => [
                    'id' => $wallet->id,
                    'name' => $wallet->name,
                    'is_shared' => $wallet->is_shared,
                ],
                'current_date' => date('Y-m-d'),
                'current_month' => date('Y-m'),
                'financial_summary' => [
                    'total_income' => $income,
                    'total_expense' => $expense,
                    'net_balance' => $income - $expense,
                    'unpaid_utilities' => $unpaidUtilities,
                    'total_savings' => $savingsTotal,
                    'total_debts' => $debtsTotal,
                    'little_loom_revenue' => $ordersTotal,
                    'little_loom_pending_orders_count' => $pendingOrdersCount,
                ],
            ];
        }

        $response = $this->ai->ask($prompt, $context);

        return ['answer' => $response];
    }

    private function utilityHouseholdPortion(Utility $utility, Household $household): float
    {
        $s = $this->crypto->utilityResolved($utility, $household);
        $total = (float) ($s['total'] ?? 0);
        $rule = $s['split_rule'] ?? 'shared';

        return $rule === 'shared' ? $total / 2 : ($rule === 'dani-private' ? $total : 0);
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

}
