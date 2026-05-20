<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\Meter;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\Utility;
use App\Models\Household;
use App\Services\EncryptedRecordService;
use App\Services\HouseholdCipherService;
use App\Services\OpenAIService;
use App\Services\TransactionSensitiveData;
use Illuminate\Http\Request;

class AIFinanceController extends Controller
{
    public function __construct(
        private OpenAIService $ai,
        private TransactionSensitiveData $sensitive,
        private HouseholdCipherService $cipher,
        private EncryptedRecordService $crypto,
    ) {}

    private function household(Request $request): Household
    {
        $household = $request->user()->household;
        $this->cipher->ensureCipherKey($household);

        return $household;
    }

    private function envelope(array $data, bool $fallbackUsed = false, ?string $failureReason = null): array
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

    private function normalizeJson(string $raw): string
    {
        if (preg_match('/\{.*\}/s', $raw, $matches)) {
            return $matches[0];
        }
        return $raw;
    }

    public function autoCategorizeTransaction(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|max:255',
            'type' => 'nullable|in:income,expense',
            'amount' => 'nullable|numeric',
            'candidate_categories' => 'required|array|min:1',
            'candidate_categories.*' => 'string'
        ]);

        $fallback = function () use ($validated) {
            $description = mb_strtolower($validated['description']);
            $categories = $validated['candidate_categories'];
            $picked = $categories[0];

            $keywords = [
                'Rezsi' => ['eon', 'mvm', 'villany', 'gáz', 'viz', 'víz', 'csatorna', 'rezsi'],
                'Kaja' => ['aldi', 'lidl', 'spar', 'tesco', 'auchan', 'kaja', 'etel', 'étel', 'food'],
                'Tankolás' => ['mol', 'omv', 'shell', 'tank'],
                'Autó' => ['szerviz', 'gumi', 'parkol', 'auto', 'autó'],
                'Streaming, Subscriptions' => ['spotify', 'netflix', 'youtube', 'apple', 'subscription'],
            ];

            foreach ($keywords as $category => $words) {
                if (!in_array($category, $categories, true)) {
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
                'rationale' => 'Szabályalapú fallback besorolás kulcsszavak alapján.'
            ];
        };

        try {
            $prompt = "Kategorizáld ezt a tranzakciót a megadott kategóriák egyikébe, és adj vissza KIZÁRÓLAG érvényes JSON-t ilyen mezőkkel: category (string), confidence (0..1 number), normalized_description (string), rationale (string).\n".
                "Leírás: {$validated['description']}\n".
                "Típus: ".($validated['type'] ?? 'ismeretlen')."\n".
                "Összeg: ".($validated['amount'] ?? 'ismeretlen')."\n".
                "Engedélyezett kategóriák: ".implode(', ', $validated['candidate_categories'])."\n".
                "A category mező csak a felsorolt kategóriák egyike lehet.";

            $raw = $this->ai->ask($prompt);
            $decoded = json_decode($this->normalizeJson($raw), true, 512, JSON_THROW_ON_ERROR);
            if (!isset($decoded['category']) || !in_array($decoded['category'], $validated['candidate_categories'], true)) {
                throw new \RuntimeException('Invalid category from model');
            }
            $result = [
                'category' => $decoded['category'],
                'confidence' => max(0, min(1, (float)($decoded['confidence'] ?? 0.6))),
                'normalized_description' => (string)($decoded['normalized_description'] ?? trim($validated['description'])),
                'rationale' => (string)($decoded['rationale'] ?? 'AI kategorizálás.')
            ];

            return response()->json($this->envelope($result));
        } catch (\Throwable $e) {
            return response()->json($this->envelope($fallback(), true, $e->getMessage()));
        }
    }

    public function overspendRootCause(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $household = $this->household($request);
        $monthPrefix = sprintf('%04d-%02d', $validated['year'], $validated['month']);

        $transactions = Transaction::where('household_id', $household->id)
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

        $utilityPaid = $utilities->whereNotNull('paid_date')->sum(function ($u) use ($household) {
            $s = $this->crypto->utilityResolved($u, $household);
            $total = (float) ($s['total'] ?? 0);
            $rule = $s['split_rule'] ?? 'shared';

            return $rule === 'shared' ? $total / 2 : ($rule === 'dani-private' ? $total : 0);
        });
        $monthlyBalance = (float)$incomeReceived - (float)$expensePaid - (float)$utilityPaid;
        $overspendAmount = $monthlyBalance < 0 ? abs($monthlyBalance) : 0.0;

        // Category totals also use actual sub-item spending for budget items (exclude reserves)
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
                "Top kategóriák: ".json_encode($topDrivers);
            $raw = $this->ai->ask($prompt);
            $decoded = json_decode($this->normalizeJson($raw), true, 512, JSON_THROW_ON_ERROR);
            return response()->json($this->envelope($decoded));
        } catch (\Throwable $e) {
            return response()->json($this->envelope($fallbackPayload, true, $e->getMessage()));
        }
    }

    public function cashflowForecast(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $household = $this->household($request);
        $monthPrefix = sprintf('%04d-%02d', $validated['year'], $validated['month']);
        $transactions = Transaction::where('household_id', $household->id)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->with('items')
            ->get();
        $utilities = Utility::where('household_id', $household->id)
            ->where('due_date', 'like', $monthPrefix.'%')
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

        $pendingUtility = (float) $utilities->whereNull('paid_date')->sum(function ($u) use ($household) {
            $s = $this->crypto->utilityResolved($u, $household);
            $total = (float) ($s['total'] ?? 0);
            $rule = $s['split_rule'] ?? 'shared';

            return $rule === 'shared' ? $total / 2 : ($rule === 'dani-private' ? $total : 0);
        });

        $p50 = $receivedIncome + ($pendingIncome * 0.8) - $paidExpense - ($pendingExpense * 0.85) - ($pendingUtility * 0.85);
        $payload = [
            'forecast_balance' => round($p50, 2),
            'p10' => round($p50 - max(30000, abs($p50) * 0.2), 2),
            'p50' => round($p50, 2),
            'p90' => round($p50 + max(30000, abs($p50) * 0.2), 2),
            'assumptions' => [
                'pending_income_probability' => 0.8,
                'pending_expense_probability' => 0.85,
                'pending_utility_probability' => 0.85,
            ],
            'risk_flags' => $p50 < 0 ? ['Hónap végi negatív egyenleg kockázat.'] : [],
        ];

        return response()->json($this->envelope($payload));
    }

    public function utilityAnomalies(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $household = $this->household($request);
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

        return response()->json($this->envelope([
            'anomalies' => $anomalies,
            'recommendations' => count($anomalies)
                ? ['Ellenőrizd az érintett mérőkhöz tartozó utolsó leolvasást és reset jelöléseket.']
                : ['Nem látható jelentős rezsi anomália a kiválasztott hónapban.'],
        ]));
    }

    public function savingsRecommendations(Request $request)
    {
        $validated = $request->validate([
            'goals' => 'required|array|min:1',
            'goals.*.name' => 'required|string',
            'goals.*.target_amount' => 'required|numeric|min:1',
            'goals.*.target_date' => 'required|date',
            'goals.*.priority' => 'nullable|integer|min:1|max:5',
            'constraints.min_buffer' => 'nullable|numeric|min:0',
        ]);

        $household = $this->household($request);
        $savings = Saving::where('household_id', $household->id)->with('ledger')->get();
        $currentTotal = (float) $savings->sum(function ($s) use ($household) {
            return $s->ledger->sum(fn ($e) => (float) ($this->crypto->ledgerResolved($e, $household)['amount'] ?? 0));
        });
        $goals = collect($validated['goals']);
        $totalTarget = (float)$goals->sum('target_amount');
        $gap = max(0, $totalTarget - $currentTotal);

        $minMonths = max(1, $goals->map(function ($goal) {
            return now()->diffInMonths(\Carbon\Carbon::parse($goal['target_date']), false);
        })->filter(fn ($m) => $m > 0)->min() ?? 12);
        $monthlyNeed = $gap > 0 ? round($gap / $minMonths, 2) : 0;

        $plan = $goals->map(function ($goal) use ($totalTarget, $monthlyNeed) {
            $share = $totalTarget > 0 ? ((float)$goal['target_amount'] / $totalTarget) : 0;
            return [
                'goal' => $goal['name'],
                'monthly_allocation' => round($monthlyNeed * $share, 2),
                'target_date' => $goal['target_date'],
            ];
        })->values()->all();

        return response()->json($this->envelope([
            'monthly_allocation_plan' => $plan,
            'projected_completion' => $plan,
            'tradeoffs' => $gap > 0
                ? ['Ha magasabb havi félrerakást vállalsz, gyorsabban teljesül a célcsomag.']
                : ['A jelenlegi széf állomány fedezi a megadott célokat.'],
        ]));
    }

    public function optimizeDebts(Request $request)
    {
        $strategy = $request->input('strategy', 'avalanche');
        $household = $this->household($request);
        $debts = Debt::where('household_id', $household->id)->get();
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

        return response()->json($this->envelope([
            'strategy' => in_array($strategy, ['avalanche', 'snowball'], true) ? $strategy : 'avalanche',
            'schedule' => $schedule,
            'payoff_date' => now()->addMonths(max(1, count($schedule) * 3))->toDateString(),
            'total_interest' => round($active->sum(function ($d) use ($household) {
                $s = $this->crypto->debtResolved($d, $household);
                $remaining = (float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0);

                return $remaining * ((float) ($s['annual_interest_rate'] ?? 0) / 100) * 0.5;
            }), 2),
            'alternatives' => ['avalanche', 'snowball'],
        ]));
    }

    public function weeklyBriefing(Request $request)
    {
        $household = $this->household($request);
        $weekStart = $request->query('week_start')
            ? \Carbon\Carbon::parse($request->query('week_start'))->startOfDay()
            : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $transactions = Transaction::where('household_id', $household->id)
            ->whereBetween('due_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
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

        return response()->json($this->envelope($payload));
    }
}

