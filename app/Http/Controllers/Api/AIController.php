<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OpenAIService;
use Illuminate\Http\Request;

class AIController extends Controller
{
    protected $ai;

    public function __construct(OpenAIService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * Handle generic financial AI queries.
     */
    public function query(Request $request)
    {
        $request->validate([
            'prompt' => 'required|string|max:1000',
            'include_context' => 'boolean'
        ]);

        $context = [];
        if ($request->include_context) {
            $user = $request->user();
            $household = $user->household;
            
            // Summarize Transactions
            $transactions = \App\Models\Transaction::where('household_id', $user->household_id)->get();
            $income = (float)$transactions->where('type', 'income')->sum('amount');
            $expense = (float)$transactions->where('type', 'expense')->sum('amount');
            
            // Summarize Utilities
            $utilities = \App\Models\Utility::where('household_id', $user->household_id)->get();
            $unpaidUtilities = (float)$utilities->whereNull('paid_date')->sum(function($u) {
                return $u->split_rule === 'shared' ? $u->total / 2 : ($u->split_rule === 'dani-private' ? $u->total : 0);
            });

            // Summarize Savings
            $savings = \App\Models\Saving::where('household_id', $user->household_id)->with('ledger')->get();
            $savingsTotal = (float)$savings->sum(fn($s) => $s->ledger->sum('amount'));

            // Summarize Debts
            $debts = \App\Models\Debt::where('household_id', $user->household_id)->get();
            $debtsTotal = (float)$debts->sum(fn($d) => $d->target_amount - $d->paid_amount);

            // Summarize Little Loom Business
            $orders = \App\Models\BusinessOrder::where('household_id', $user->household_id)->get();
            $ordersTotal = (float)$orders->sum('amount');
            $pendingOrdersCount = $orders->where('state', '!=', 'RENDBEN')->count();

            $context = [
                'user_name' => $user->first_name . ' ' . $user->last_name,
                'household' => $household ? $household->name : 'Nincs háztartás',
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
                    'little_loom_pending_orders_count' => $pendingOrdersCount
                ]
            ];
        }

        $response = $this->ai->ask($request->prompt, $context);

        return response()->json([
            'answer' => $response
        ]);
    }
}
