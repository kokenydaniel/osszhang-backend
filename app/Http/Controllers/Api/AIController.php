<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EncryptedRecordService;
use App\Services\HouseholdCipherService;
use App\Services\OpenAIService;
use App\Services\TransactionSensitiveData;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(
        protected OpenAIService $ai,
        protected TransactionSensitiveData $sensitive,
        protected HouseholdCipherService $cipher,
        protected EncryptedRecordService $crypto,
    ) {}

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
            $transactions = \App\Models\Transaction::where('household_id', $user->household_id)->with('items')->get();
            if ($household) {
                $this->cipher->ensureCipherKey($household);
            }
            $income = (float) $transactions
                ->where('type', 'income')
                ->sum(fn ($t) => $this->sensitive->resolvedAmount($t, $household));
            $expense = (float) $transactions
                ->where('type', 'expense')
                ->sum(fn ($t) => $this->sensitive->expenseTotal($t, $household));
            
            // Summarize Utilities
            $utilities = \App\Models\Utility::where('household_id', $user->household_id)->get();
            $unpaidUtilities = (float) $utilities->whereNull('paid_date')->sum(function ($u) use ($household) {
                $s = $this->crypto->utilityResolved($u, $household);
                $total = (float) ($s['total'] ?? 0);
                $rule = $s['split_rule'] ?? 'shared';

                return $rule === 'shared' ? $total / 2 : ($rule === 'dani-private' ? $total : 0);
            });

            // Summarize Savings
            $savings = \App\Models\Saving::where('household_id', $user->household_id)->with('ledger')->get();
            $savingsTotal = (float) $savings->sum(function ($s) use ($household) {
                return $s->ledger->sum(fn ($e) => (float) ($this->crypto->ledgerResolved($e, $household)['amount'] ?? 0));
            });

            // Summarize Debts
            $debts = \App\Models\Debt::where('household_id', $user->household_id)->get();
            $debtsTotal = (float) $debts->sum(function ($d) use ($household) {
                $s = $this->crypto->debtResolved($d, $household);

                return (float) ($s['target_amount'] ?? 0) - (float) ($s['paid_amount'] ?? 0);
            });

            // Summarize Little Loom Business
            $orders = \App\Models\BusinessOrder::where('household_id', $user->household_id)->get();
            $ordersTotal = (float) $orders->sum(fn ($o) => (float) ($this->crypto->businessOrderResolved($o, $household)['amount'] ?? 0));
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
