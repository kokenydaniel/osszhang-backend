<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Models\Meter;
use App\Models\Saving;
use App\Models\Transaction;
use App\Models\Utility;
use App\Services\OpenAIService;
use Illuminate\Http\Request;

class AIFinanceController extends Controller
{
    public function __construct(private OpenAIService $ai)
    {
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

        $householdId = $request->user()->household_id;
        $monthPrefix = sprintf('%04d-%02d', $validated['year'], $validated['month']);

        $transactions = Transaction::where('household_id', $householdId)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->with('items')
            ->get();
        $utilities = Utility::where('household_id', $householdId)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->get();

        $incomeReceived = $transactions->where('type', 'income')->where('is_reserve', false)->whereNotNull('paid_date')->sum('amount');

        // For budget items with sub-items, use actual sub-item totals instead of budget amount
        // Exclude reserve transactions from cashflow calculations
        $expensePaid = $transactions->where('type', 'expense')->where('is_reserve', false)->sum(function ($t) {
            if ($t->is_budget && $t->items->count() > 0) {
                return $t->items->sum(fn ($i) => abs($i->amount));
            }
            return $t->paid_date ? (float)$t->amount : 0;
        });

        $utilityPaid = $utilities->whereNotNull('paid_date')->sum(function ($u) {
            return $u->split_rule === 'shared' ? $u->total / 2 : ($u->split_rule === 'dani-private' ? $u->total : 0);
        });
        $monthlyBalance = (float)$incomeReceived - (float)$expensePaid - (float)$utilityPaid;
        $overspendAmount = $monthlyBalance < 0 ? abs($monthlyBalance) : 0.0;

        // Category totals also use actual sub-item spending for budget items (exclude reserves)
        $categoryTotals = $transactions->where('type', 'expense')->where('is_reserve', false)->groupBy('category')->map(function ($rows) {
            return $rows->sum(function ($t) {
                if ($t->is_budget && $t->items->count() > 0) {
                    return (float)$t->items->sum(fn ($i) => abs($i->amount));
                }
                return (float)$t->amount;
            });
        });
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

        $householdId = $request->user()->household_id;
        $monthPrefix = sprintf('%04d-%02d', $validated['year'], $validated['month']);
        $transactions = Transaction::where('household_id', $householdId)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->with('items')
            ->get();
        $utilities = Utility::where('household_id', $householdId)
            ->where('due_date', 'like', $monthPrefix.'%')
            ->get();

        // Exclude reserve transactions from cashflow calculations
        $receivedIncome = (float)$transactions->where('type', 'income')->where('is_reserve', false)->whereNotNull('paid_date')->sum('amount');
        $pendingIncome = (float)$transactions->where('type', 'income')->where('is_reserve', false)->whereNull('paid_date')->sum('amount');

        // For budget items with sub-items, use actual sub-item totals
        $paidExpense = (float)$transactions->where('type', 'expense')->where('is_reserve', false)->sum(function ($t) {
            if ($t->is_budget && $t->items->count() > 0) {
                return $t->items->sum(fn ($i) => abs($i->amount));
            }
            return $t->paid_date ? (float)$t->amount : 0;
        });
        $pendingExpense = (float)$transactions->where('type', 'expense')->where('is_reserve', false)->whereNull('paid_date')
            ->filter(fn ($t) => !$t->is_budget)
            ->sum('amount');

        $pendingUtility = (float)$utilities->whereNull('paid_date')->sum(function ($u) {
            return $u->split_rule === 'shared' ? $u->total / 2 : ($u->split_rule === 'dani-private' ? $u->total : 0);
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

        $meters = Meter::where('household_id', $request->user()->household_id)->with('readings')->get();
        $anomalies = [];
        foreach ($meters as $meter) {
            $target = $meter->readings->first(fn ($r) => (int)$r->year === (int)$validated['year'] && (int)$r->month === (int)$validated['month']);
            if (!$target) {
                continue;
            }
            $historical = $meter->readings->filter(fn ($r) => !((int)$r->year === (int)$validated['year'] && (int)$r->month === (int)$validated['month']))
                ->pluck('consumption')
                ->map(fn ($x) => max(0, (float)$x))
                ->values();

            if ($historical->count() < 3) {
                continue;
            }
            $avg = $historical->avg();
            $threshold = max(1, $avg * 0.35);
            $diff = (float)$target->consumption - (float)$avg;
            if (abs($diff) > $threshold) {
                $anomalies[] = [
                    'meter_id' => $meter->id,
                    'meter_name' => $meter->name,
                    'expected' => round($avg, 2),
                    'actual' => (float)$target->consumption,
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

        $savings = Saving::where('household_id', $request->user()->household_id)->with('ledger')->get();
        $currentTotal = (float)$savings->sum(fn ($s) => $s->ledger->sum('amount'));
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
        $debts = Debt::where('household_id', $request->user()->household_id)->get();
        $active = $debts->filter(fn ($d) => ((float)$d->target_amount - (float)$d->paid_amount) > 0)->values();

        $ordered = $active->sortBy(function ($debt) use ($strategy) {
            if ($strategy === 'snowball') {
                return ((float)$debt->target_amount - (float)$debt->paid_amount);
            }
            return -1 * (float)($debt->annual_interest_rate ?? 0);
        })->values();

        $schedule = $ordered->map(function ($debt, $index) {
            $remaining = (float)$debt->target_amount - (float)$debt->paid_amount;
            return [
                'rank' => $index + 1,
                'debt_id' => $debt->id,
                'name' => $debt->name,
                'remaining' => round($remaining, 2),
                'recommended_extra_payment' => round(max((float)($debt->minimum_payment ?? 0), $remaining * 0.08), 2),
            ];
        })->all();

        return response()->json($this->envelope([
            'strategy' => in_array($strategy, ['avalanche', 'snowball'], true) ? $strategy : 'avalanche',
            'schedule' => $schedule,
            'payoff_date' => now()->addMonths(max(1, count($schedule) * 3))->toDateString(),
            'total_interest' => round($active->sum(fn ($d) => ((float)($d->target_amount - $d->paid_amount)) * ((float)($d->annual_interest_rate ?? 0) / 100) * 0.5), 2),
            'alternatives' => ['avalanche', 'snowball'],
        ]));
    }

    public function weeklyBriefing(Request $request)
    {
        $householdId = $request->user()->household_id;
        $weekStart = $request->query('week_start')
            ? \Carbon\Carbon::parse($request->query('week_start'))->startOfDay()
            : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();

        $transactions = Transaction::where('household_id', $householdId)
            ->whereBetween('due_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();
        $income = (float)$transactions->where('type', 'income')->sum('amount');
        $expense = (float)$transactions->where('type', 'expense')->sum('amount');
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

