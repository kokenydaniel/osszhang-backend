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
use App\Support\AiUsageContext;
use App\Services\Finance\PaymentPriorityCalculator;
use App\Services\Finance\VatEstimationCalculator;
use Carbon\Carbon;

class AIFinanceService
{
    public function __construct(
        private OpenAIService $ai,
        private TransactionSensitiveData $sensitive,
        private HouseholdCipherService $cipher,
        private EncryptedRecordService $crypto,
        private WalletProvisioningService $wallets,
        private PaymentPriorityCalculator $paymentPriorityCalculator,
        private VatEstimationCalculator $vatEstimationCalculator,
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

    public function autoCategorizeTransaction(User $user, array $validated): array
    {
        $usageContext = $this->aiUsage($user->household, $user, 'auto_categorize');
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
                $usageContext,
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

        $utilityPaid = (float) $utilities
            ->whereNotNull('paid_date')
            ->sum(fn ($u) => $this->utilityHouseholdPortion($u, $household));

        $monthlyBalance = round((float) $incomeReceived - (float) $expensePaid - (float) $utilityPaid, 2);
        $overspendAmount = $monthlyBalance < 0 ? round(abs($monthlyBalance), 2) : 0.0;

        $categoryTotals = $transactions->where('type', 'expense')->where('is_reserve', false)
            ->groupBy(fn ($t) => $this->sensitive->resolvedCategory($t, $household))
            ->map(fn ($rows) => $rows->sum(fn ($t) => $this->sensitive->expenseTotal($t, $household)));

        $rezsiTotal = (float) $utilities->sum(
            fn ($u) => $this->utilityHouseholdPortion($u, $household),
        );
        if ($rezsiTotal > 0) {
            $categoryTotals = $categoryTotals->put(
                'Rezsi',
                ($categoryTotals['Rezsi'] ?? 0) + $rezsiTotal,
            );
        }

        $topDrivers = $categoryTotals
            ->sortDesc()
            ->take(3)
            ->map(fn ($amount, $category) => [
                'category' => $category,
                'amount' => round((float) $amount, 2),
            ])
            ->values()
            ->all();

        $payload = [
            'status' => $overspendAmount > 0 ? 'overspent' : 'ok',
            'overspend_amount' => $overspendAmount,
            'monthly_balance' => $monthlyBalance,
            'income_received' => round((float) $incomeReceived, 2),
            'spent_this_month' => round((float) $expensePaid + (float) $utilityPaid, 2),
            'top_drivers' => $topDrivers,
            'actions' => $overspendAmount > 0
                ? ['Fagyaszd a legnagyobb kiadási kategóriát 7 napra.', 'Nézd át a függő tételeket és halassz 1-2 alacsony prioritásút.']
                : ['A havi kereted kontroll alatt van, tartsd az aktuális tempót.'],
            'confidence' => 0.85,
        ];

        try {
            $prompt = "Adj vissza KIZÁRÓLAG JSON-t egyetlen mezővel: actions (max 3 rövid magyar teendő).\n".
                "Hónap: {$monthPrefix}\n".
                "Havi egyenleg (befolyt bevétel − kifizetett kiadások): {$monthlyBalance} Ft\n".
                'Státusz: '.($overspendAmount > 0 ? 'túlköltés' : 'rendben')."\n".
                'Top kiadási kategóriák: '.json_encode($topDrivers, JSON_UNESCAPED_UNICODE);
            $decoded = $this->ai->askJson(
                $prompt,
                'Te egy pénzügyi tanácsadó vagy. A számokat már kiszámoltuk — csak gyakorlati teendőket adj vissza érvényes JSON-ban.',
                $this->aiUsage($household, $user, 'overspend_analysis'),
            );

            $actions = $decoded['actions'] ?? $payload['actions'];
            if (! is_array($actions)) {
                $actions = $payload['actions'];
            }
            $actions = array_values(array_filter(array_map(
                static fn ($action) => trim((string) $action),
                array_slice($actions, 0, 3),
            )));

            if ($actions === []) {
                $actions = $payload['actions'];
            }

            $payload['actions'] = $actions;

            return $this->envelope($payload);
        } catch (\Throwable $e) {
            return $this->envelope($payload, true, $e->getMessage());
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

    public function aiCfo(Household $household, User $user, array $validated): array
    {
        $this->ensureHousehold($household);
        $this->resolveWallet($user, $validated['wallet_id'] ?? null);

        $monthPrefix = sprintf('%04d-%02d', $validated['year'], $validated['month']);
        $metrics = $validated;
        $snapshot = $this->buildAiCfoHumanSnapshot($monthPrefix, $metrics);

        $monthlyBalance = (float) ($metrics['monthly_balance'] ?? 0);
        $disposableRemaining = (float) ($metrics['disposable_remaining'] ?? 0);
        $lockedSavings = (float) ($metrics['locked_savings'] ?? 0);
        $totalPending = (float) ($metrics['total_pending'] ?? 0);
        $totalDebts = (float) ($metrics['total_debts'] ?? 0);
        $spentThisMonth = (float) ($metrics['spent_this_month'] ?? 0);

        $fallback = function () use ($monthPrefix, $monthlyBalance, $disposableRemaining, $lockedSavings, $totalPending, $totalDebts, $spentThisMonth, $metrics) {
            $fmt = fn (float $n) => number_format($n, 0, ',', ' ').' Ft';

            $summary = sprintf(
                'A %s hónapban a havi egyenleged %s, a biztonságosan elkölthető összeg (Marad) pedig %s.',
                $monthPrefix,
                $fmt($monthlyBalance),
                $fmt($disposableRemaining),
            );

            $tips = [];
            if ($disposableRemaining < 0) {
                $tips[] = sprintf(
                    'A Marad értéked %s — csökkentsd a fizetendő %s összegű tételeit, vagy halaszd az alacsony prioritású kiadásokat.',
                    $fmt($disposableRemaining),
                    $fmt($totalPending),
                );
            } elseif ($disposableRemaining > 0) {
                $tips[] = sprintf(
                    'Ebben a hónapban biztonságosan még %s költhetsz — tartsd szem előtt, hogy %s zárolt megtakarítás.',
                    $fmt($disposableRemaining),
                    $fmt($lockedSavings),
                );
            }
            foreach ($metrics['savings_goals'] ?? [] as $goal) {
                if (($goal['remaining_amount'] ?? 0) > 0) {
                    $tips[] = sprintf(
                        'A „%s” célhoz még %s kell (eddig %s a %s-ból).',
                        $goal['title'],
                        $fmt((float) $goal['remaining_amount']),
                        $fmt((float) $goal['current_amount']),
                        $fmt((float) $goal['target_amount']),
                    );
                    break;
                }
            }
            if ($totalDebts > 0 && $disposableRemaining > 0) {
                $tips[] = sprintf(
                    'A fennmaradó tartozásod %s — csak a Marad (%s) keretén belül emeld a törlesztést.',
                    $fmt($totalDebts),
                    $fmt($disposableRemaining),
                );
            }
            if (count($tips) === 0) {
                $tips[] = sprintf('A Marad értéked %s — tartsd ezt a tempót.', $fmt($disposableRemaining));
            }

            $warnings = [];
            if ($disposableRemaining < 0) {
                $warnings[] = sprintf('A biztonságosan elkölthető összeg negatív: %s.', $fmt($disposableRemaining));
            }
            if ($monthlyBalance < 0) {
                $warnings[] = sprintf('A havi egyenleged negatív: %s.', $fmt($monthlyBalance));
            }
            if ((float) ($metrics['overdue_total'] ?? 0) > 0) {
                $warnings[] = sprintf('Lejárt fizetnivalód: %s.', $fmt((float) $metrics['overdue_total']));
            }
            foreach ($metrics['top_spending_categories'] ?? [] as $cat) {
                if (($cat['amount'] ?? 0) > max(50000, $spentThisMonth * 0.35)) {
                    $warnings[] = sprintf(
                        'Magas kiadás a „%s” kategóriában: %s.',
                        $cat['category'] ?? 'Ismeretlen',
                        $fmt((float) ($cat['amount'] ?? 0)),
                    );
                    break;
                }
            }

            return [
                'summary' => $summary,
                'tips' => array_slice($tips, 0, 3),
                'warnings' => $warnings,
            ];
        };

        $systemPrompt = implode("\n", [
            'ACT AS A STRICT, DATA-DRIVEN FINANCIAL ADVISOR.',
            'WRITE IN NATURAL, HUMAN-SOUNDING HUNGARIAN. NEVER output JSON keys, variable names, or underscores.',
            'Format all numbers with spaces for thousands and " Ft" (e.g., "227 491 Ft"). NEVER use "HUF".',
            'YOU MUST ONLY USE THE EXACT NUMBERS PROVIDED IN THE CONTEXT. DO NOT INVENT OR GUESS ANY FINANCIAL DATA.',
            'The user total bank balance includes locked savings — DO NOT suggest spending locked savings.',
            'The disposable remaining (Marad) is the EXACT amount they can safely spend this month. Base actionable advice heavily on this number.',
            'DO NOT GIVE GENERIC ADVICE like "create a budget" or "track your spending".',
            'Return ONLY valid JSON with fields: summary (string, exactly 2 sentences), tips (string array, 2-3 items), warnings (string array, empty if none).',
        ]);

        try {
            $prompt = "Elemezd az alábbi pénzügyi pillanatképet, és készíts JSON választ.\n".
                "Használj természetes magyar fogalmakat, és KIZÁRÓLAG a megadott számokat.\n\n".
                json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

            $decoded = $this->ai->askJson(
                $prompt,
                $systemPrompt,
                $this->aiUsage($household, $user, 'monthly_advisor'),
            );

            $result = [
                'summary' => trim((string) ($decoded['summary'] ?? '')),
                'tips' => array_values(array_filter(array_map('strval', $decoded['tips'] ?? []))),
                'warnings' => array_values(array_filter(array_map('strval', $decoded['warnings'] ?? []))),
            ];

            if ($result['summary'] === '') {
                throw new \RuntimeException('Empty AI CFO summary');
            }

            return $this->envelope($result);
        } catch (\Throwable $e) {
            return $this->envelope($fallback(), true, $e->getMessage());
        }
    }

    private function buildAiCfoHumanSnapshot(string $monthPrefix, array $metrics): array
    {
        $fmt = fn (float $n) => number_format($n, 0, ',', ' ').' Ft';

        $goals = collect($metrics['savings_goals'] ?? [])->map(fn (array $goal) => [
            'Cél neve' => (string) ($goal['title'] ?? ''),
            'Célösszeg' => $fmt((float) ($goal['target_amount'] ?? 0)),
            'Eddig félretett' => $fmt((float) ($goal['current_amount'] ?? 0)),
            'Hátralévő' => $fmt((float) ($goal['remaining_amount'] ?? 0)),
            'Határidő' => (string) ($goal['target_date'] ?? 'nincs megadva'),
        ])->values()->all();

        $debts = collect($metrics['debts'] ?? [])->map(fn (array $debt) => [
            'Tartozás neve' => (string) ($debt['name'] ?? ''),
            'Fennmaradó összeg' => $fmt((float) ($debt['remaining'] ?? 0)),
        ])->values()->all();

        $categories = collect($metrics['top_spending_categories'] ?? [])->map(fn (array $cat) => [
            'Kategória' => (string) ($cat['category'] ?? ''),
            'Összeg' => $fmt((float) ($cat['amount'] ?? 0)),
        ])->values()->all();

        return [
            'Hónap' => $monthPrefix,
            'Bankszámla egyenleg (zárolt megtakarítással együtt)' => $fmt((float) ($metrics['total_balance'] ?? 0)),
            'Zárolt megtakarítás (nem költhető el)' => $fmt((float) ($metrics['locked_savings'] ?? 0)),
            'Fizetendő összesen' => $fmt((float) ($metrics['total_pending'] ?? 0)),
            'Marad (biztonságosan elkölthető)' => $fmt((float) ($metrics['disposable_remaining'] ?? 0)),
            'Havi egyenleg (bevétel − kiadás)' => $fmt((float) ($metrics['monthly_balance'] ?? 0)),
            'Lejárt fizetnivaló' => $fmt((float) ($metrics['overdue_total'] ?? 0)),
            'Bevétel ebben a hónapban' => $fmt((float) ($metrics['income_received'] ?? 0)),
            'Kiadás ebben a hónapban' => $fmt((float) ($metrics['spent_this_month'] ?? 0)),
            'Összes tartozás' => $fmt((float) ($metrics['total_debts'] ?? 0)),
            'Legnagyobb kiadási kategóriák' => $categories,
            'Megtakarítási célok' => $goals,
            'Tartozások' => $debts,
        ];
    }

    public function travelPlan(User $user, array $validated): array
    {
        $destination = trim($validated['destination']);
        $durationDays = max(1, (int) $validated['duration_days']);
        $totalBudget = round((float) $validated['total_budget'], 2);

        $travelSystemPrompt = 'Te egy utazástervező és pénzügyi tanácsadó vagy. Adj vissza KIZÁRÓLAG érvényes JSON-t a kért mezőkkel, magyar nyelven. '.
            'NE találj ki irreálisan alacsony árakat, hogy illeszkedjen a felhasználó költségkeretéhez. '.
            'Ha a total_budget matematikailag vagy realitás alapján lehetetlen az adott destination és duration_days mellett '.
            '(pl. 10 000 Ft ötsz napos londoni utazásra), NE hamisíts olcsó árakat. '.
            'Ehelyett számíts ki egy REALISZTIKUS MINIMUM költségvetést az úti célhoz, és a terv total_estimated_cost mezője ezt tükrözze. '.
            'Ilyenkor kötelező warning (string) mező: udvarias magyar magyarázat, hogy a kért keret túl alacsony volt, ezért a terv reális minimum költségeket mutat. '.
            'Ha a keret reális, a warning mezőt hagyd el vagy null legyen.';

        try {
            $prompt = "Tervezz egy személyre szabott utazást magyar nyelven. Adj vissza KIZÁRÓLAG érvényes JSON-t ezekkel a mezőkkel:\n".
                "- destination (string)\n".
                "- duration_days (integer)\n".
                "- total_budget (number, HUF) — a felhasználó által megadott keret, ne módosítsd\n".
                "- daily_itinerary (array): minden elem { day (int), title (string), activities (string[]), estimated_daily_cost (number) }\n".
                "- cost_breakdown (object): { accommodation, food, activities, transport } — mind number, HUF\n".
                "- total_estimated_cost (number): a cost_breakdown összege, reális piaci árak alapján\n".
                "- summary (string): 1 rövid mondat az utazásról\n".
                "- warning (string, opcionális): ha a megadott keret irreálisan alacsony, udvarias magyar figyelmeztetés\n".
                "FONTOS: Ha a keret lehetetlen, a total_estimated_cost legyen a reális minimum, NE a megadott keret.\n".
                "Úti cél: {$destination}\n".
                "Időtartam: {$durationDays} nap\n".
                "Felhasználó költségkerete: {$totalBudget} HUF";

            $decoded = $this->ai->askJson(
                $prompt,
                $travelSystemPrompt,
                $this->aiUsage($user->household, $user, 'travel_planner'),
            );
            $result = $this->normalizeTravelPlanPayload($decoded, $destination, $durationDays, $totalBudget);

            if (count($result['daily_itinerary']) === 0) {
                throw new \RuntimeException('Empty travel itinerary');
            }

            return $this->envelope($result);
        } catch (\Throwable $e) {
            return $this->envelope(
                $this->buildTravelPlanFallback($destination, $durationDays, $totalBudget),
                true,
                $e->getMessage(),
            );
        }
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

        $response = $this->ai->ask(
            $prompt,
            $context,
            $this->aiUsage($user->household, $user, 'ai_query'),
        );

        return ['answer' => $response];
    }

    private function aiUsage(?Household $household, ?User $user, string $feature): ?AiUsageContext
    {
        if ($household === null) {
            return null;
        }

        return new AiUsageContext($household->id, $user?->id, $feature);
    }

    private function utilityHouseholdPortion(Utility $utility, Household $household): float
    {
        $s = $this->crypto->utilityResolved($utility, $household);
        $total = (float) ($s['total'] ?? 0);
        $rule = $s['split_rule'] ?? 'shared';

        return $rule === 'shared' ? $total / 2 : ($rule === 'dani-private' ? $total : 0);
    }

    private function normalizeTravelPlanPayload(
        array $decoded,
        string $destination,
        int $durationDays,
        float $requestedBudget,
    ): array {
        $breakdown = $decoded['cost_breakdown'] ?? [];
        $result = [
            'destination' => (string) ($decoded['destination'] ?? $destination),
            'duration_days' => max(1, (int) ($decoded['duration_days'] ?? $durationDays)),
            'total_budget' => round($requestedBudget, 2),
            'daily_itinerary' => collect($decoded['daily_itinerary'] ?? [])
                ->map(function ($row, $index) {
                    return [
                        'day' => (int) ($row['day'] ?? ($index + 1)),
                        'title' => (string) ($row['title'] ?? 'Nap '.($index + 1)),
                        'activities' => array_values(array_filter(array_map('strval', $row['activities'] ?? []))),
                        'estimated_daily_cost' => round((float) ($row['estimated_daily_cost'] ?? 0), 2),
                    ];
                })
                ->values()
                ->all(),
            'cost_breakdown' => [
                'accommodation' => round((float) ($breakdown['accommodation'] ?? 0), 2),
                'food' => round((float) ($breakdown['food'] ?? 0), 2),
                'activities' => round((float) ($breakdown['activities'] ?? 0), 2),
                'transport' => round((float) ($breakdown['transport'] ?? 0), 2),
            ],
            'total_estimated_cost' => round((float) ($decoded['total_estimated_cost'] ?? 0), 2),
            'summary' => trim((string) ($decoded['summary'] ?? '')),
        ];

        if ($result['total_estimated_cost'] <= 0) {
            $result['total_estimated_cost'] = round(array_sum($result['cost_breakdown']), 2);
        }

        $warning = trim((string) ($decoded['warning'] ?? ''));
        $realisticMinimum = $this->estimateRealisticMinimumTripBudget($destination, $durationDays);
        if ($warning === '' && $requestedBudget < $realisticMinimum * 0.75) {
            $warning = $this->buildUnrealisticBudgetWarning($destination, $durationDays, $requestedBudget, $realisticMinimum);
        }

        if ($warning !== '') {
            $result['warning'] = $warning;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function buildTravelPlanFallback(string $destination, int $durationDays, float $requestedBudget): array
    {
        $realisticMinimum = $this->estimateRealisticMinimumTripBudget($destination, $durationDays);
        $planTotal = $requestedBudget;
        $warning = null;

        if ($requestedBudget < $realisticMinimum * 0.75) {
            $planTotal = $realisticMinimum;
            $warning = $this->buildUnrealisticBudgetWarning($destination, $durationDays, $requestedBudget, $realisticMinimum);
        }

        $dailyBudget = $durationDays > 0 ? round($planTotal / $durationDays, 2) : $planTotal;
        $accommodation = round($planTotal * 0.35, 2);
        $food = round($planTotal * 0.25, 2);
        $activities = round($planTotal * 0.25, 2);
        $transport = round($planTotal * 0.15, 2);
        $itinerary = [];

        for ($day = 1; $day <= $durationDays; $day++) {
            $itinerary[] = [
                'day' => $day,
                'title' => $day === 1 ? 'Érkezés és bemelegítés' : ($day === $durationDays ? 'Búcsú és hazautazás' : "Felfedezés — {$day}. nap"),
                'activities' => $day === 1
                    ? ['Szállás elfoglalása', 'Környék felfedezése']
                    : ['Helyi látnivalók', 'Étkezés helyi specialitásokkal'],
                'estimated_daily_cost' => $dailyBudget,
            ];
        }

        $result = [
            'destination' => $destination,
            'duration_days' => $durationDays,
            'total_budget' => round($requestedBudget, 2),
            'daily_itinerary' => $itinerary,
            'cost_breakdown' => [
                'accommodation' => $accommodation,
                'food' => $food,
                'activities' => $activities,
                'transport' => $transport,
            ],
            'total_estimated_cost' => round($accommodation + $food + $activities + $transport, 2),
            'summary' => "Reális minimum alapú {$durationDays} napos terv {$destination} úti célhoz.",
        ];

        if ($warning !== null) {
            $result['warning'] = $warning;
        }

        return $result;
    }

    private function estimateRealisticMinimumTripBudget(string $destination, int $durationDays): float
    {
        $daily = $this->estimateRealisticMinimumDailyHuf($destination);

        return round($daily * max(1, $durationDays), 2);
    }

    private function estimateRealisticMinimumDailyHuf(string $destination): float
    {
        $dest = mb_strtolower($destination);
        $premium = ['london', 'párizs', 'paris', 'zürich', 'zurich', 'new york', 'tokió', 'tokyo', 'dubai', 'ibiza'];
        $mid = ['berlin', 'bécs', 'vienna', 'bécs', 'róma', 'rome', 'barcelona', 'amsterdam', 'prága', 'prague'];
        $local = ['budapest', 'balaton', 'debrecen', 'szeged', 'pecs', 'pécs', 'magyarország', 'hungary'];

        foreach ($premium as $city) {
            if (str_contains($dest, $city)) {
                return 55000;
            }
        }
        foreach ($mid as $city) {
            if (str_contains($dest, $city)) {
                return 35000;
            }
        }
        foreach ($local as $city) {
            if (str_contains($dest, $city)) {
                return 18000;
            }
        }

        return 28000;
    }

    private function buildUnrealisticBudgetWarning(
        string $destination,
        int $durationDays,
        float $requestedBudget,
        float $realisticMinimum,
    ): string {
        $requestedFormatted = number_format($requestedBudget, 0, '', ' ');
        $minimumFormatted = number_format($realisticMinimum, 0, '', ' ');

        return "A megadott {$requestedFormatted} Ft költségkeret túl alacsony egy {$durationDays} napos utazáshoz ({$destination}). "
            ."A terv ezért a reális minimum költségeket mutatja (kb. {$minimumFormatted} Ft), nem a megadott összeget.";
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

    public function paymentPriority(Household $household, User $user, array $validated): array
    {
        $this->ensureHousehold($household);
        $wallet = $this->resolveWallet($user, $validated['wallet_id'] ?? null);
        $year = (int) $validated['year'];
        $month = (int) $validated['month'];
        $queue = $this->paymentPriorityCalculator->buildQueue($household, $user, $wallet, $year, $month);
        $total = round(array_sum(array_column($queue, 'amount')), 2);

        return $this->envelope([
            'year' => $year,
            'month' => $month,
            'total_amount' => $total,
            'item_count' => count($queue),
            'items' => $queue,
            'note' => 'A sorrend a rögzített határidők és összegek alapján készült.',
        ]);
    }

    public function vatEstimate(Household $household, array $validated): array
    {
        $this->ensureHousehold($household);

        return $this->envelope(
            $this->vatEstimationCalculator->calculate(
                $household,
                (int) $validated['year'],
                (int) $validated['month'],
            ),
        );
    }

    public function costReductionSuggestions(Household $household, User $user, array $validated): array
    {
        $this->ensureHousehold($household);
        $wallet = $this->resolveWallet($user, $validated['wallet_id'] ?? null);
        $year = (int) $validated['year'];
        $month = (int) $validated['month'];

        $monthPrefix = sprintf('%04d-%02d', $year, $month);
        $transactions = Transaction::query()
            ->where('household_id', $household->id)
            ->where('wallet_id', $wallet->id)
            ->where('type', 'expense')
            ->whereNotNull('paid_date')
            ->where('paid_date', 'like', $monthPrefix.'%')
            ->get();

        $byCategory = [];
        foreach ($transactions as $tx) {
            $cat = $this->sensitive->resolvedCategory($tx, $household);
            $amount = $this->sensitive->paidExpenseTotal($tx, $household);
            if ($amount <= 0) {
                continue;
            }
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $amount;
        }
        arsort($byCategory);
        $categories = [];
        foreach ($byCategory as $name => $amount) {
            $categories[] = ['category' => $name, 'spent' => round($amount, 2)];
        }

        $fallback = fn () => [
            'suggestions' => array_map(
                fn ($row) => "A „{$row['category']}” kategóriában {$row['spent']} Ft ment el ebben a hónapban — érdemes átnézni a tételeket.",
                array_slice($categories, 0, 3),
            ),
            'categories' => $categories,
        ];

        if (count($categories) === 0) {
            return $this->envelope([
                'suggestions' => [],
                'categories' => [],
                'note' => 'Nincs kifizetett kiadás ebben a hónapban — nincs miből javaslatot készíteni.',
            ]);
        }

        try {
            $systemPrompt = implode("\n", [
                'Te egy spórolási tanácsadó vagy magyar nyelven.',
                'KIZÁRÓLAG a megadott kategória-összegeket használhatod — ne találj ki új számokat.',
                'Adj 2-4 rövid, konkrét javaslatot JSON-ben: { "suggestions": string[] }',
            ]);
            $prompt = "Havi kiadások kategóriánként (Ft):\n".json_encode($categories, JSON_UNESCAPED_UNICODE);
            $decoded = $this->ai->askJson(
                $prompt,
                $systemPrompt,
                $this->aiUsage($household, $user, 'cost_reduction'),
            );
            $suggestions = array_values(array_filter(array_map('strval', $decoded['suggestions'] ?? [])));

            return $this->envelope([
                'suggestions' => $suggestions ?: $fallback()['suggestions'],
                'categories' => $categories,
            ]);
        } catch (\Throwable $e) {
            return $this->envelope($fallback(), true, $e->getMessage());
        }
    }
}
