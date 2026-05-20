<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\UtilitySettlement;
use App\Services\HouseholdCipherService;
use App\Services\TransactionSensitiveData;
use App\Services\UtilitySettlementService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionSensitiveData $sensitive,
        private readonly HouseholdCipherService $cipher,
        private readonly UtilitySettlementService $utilitySettlements,
    ) {}

    private function household(Request $request)
    {
        $household = $request->user()->household;
        $this->cipher->ensureCipherKey($household);

        return $household;
    }

    public function index(Request $request)
    {
        $household = $this->household($request);
        $transactions = Transaction::where('household_id', $household->id)
            ->with('items')
            ->orderBy('due_date', 'desc')
            ->get();

        return response()->json(
            $transactions->map(fn ($t) => $this->sensitive->formatApi($t, $household))
        );
    }

    public function store(Request $request)
    {
        $household = $this->household($request);

        $validated = $request->validate([
            'type' => 'required|in:income,expense',
            'description' => 'required|string|max:255',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric',
            'dueDate' => 'required|date',
            'paidDate' => 'nullable|date',
            'isBudget' => 'boolean',
            'isReserve' => 'boolean',
        ]);

        $transaction = new Transaction([
            'household_id' => $household->id,
            'user_id' => $request->user()->id,
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

        return response()->json($this->sensitive->formatApi($transaction->load('items'), $household), 201);
    }

    public function update(Request $request, $id)
    {
        $household = $this->household($request);
        $transaction = Transaction::where('household_id', $household->id)
            ->with('items')
            ->findOrFail($id);

        $current = $this->sensitive->resolve($transaction, $household);

        if ($request->has('description')) {
            $current['description'] = $request->description;
        }
        if ($request->has('type')) {
            $transaction->type = $request->type;
        }
        if ($request->has('amount')) {
            $current['amount'] = (float) $request->amount;
        }
        if ($request->has('category')) {
            $current['category'] = $request->category;
        }
        if ($request->has('dueDate')) {
            $transaction->due_date = $request->dueDate;
        }
        if ($request->has('paidDate')) {
            $transaction->paid_date = $request->paidDate;
        }
        if ($request->has('isBudget')) {
            $transaction->is_budget = $request->isBudget;
        }
        if ($request->has('isReserve')) {
            $transaction->is_reserve = $request->isReserve;
        }

        $this->sensitive->persistSensitive($transaction, $household, $current);
        $transaction->save();

        return response()->json($this->sensitive->formatApi($transaction->fresh()->load('items'), $household));
    }

    public function destroy(Request $request, $id)
    {
        $household = $this->household($request);
        $transaction = Transaction::where('household_id', $household->id)->findOrFail($id);

        $settlement = UtilitySettlement::where('household_id', $household->id)
            ->where('transaction_id', $transaction->id)
            ->first();

        if ($settlement) {
            $this->utilitySettlements->revert($settlement, $household, false);
        }

        $transaction->delete();

        return response()->json(null, 204);
    }

    public function addItem(Request $request, $id)
    {
        $household = $this->household($request);
        $transaction = Transaction::where('household_id', $household->id)->with('items')->findOrFail($id);

        $v = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);

        $current = $this->sensitive->resolve($transaction, $household);
        $items = $current['subItems'] ?? [];
        $items[] = [
            'id' => -1 * (time() % 1000000),
            'date' => $v['date'],
            'amount' => (float) $v['amount'],
            'reason' => $v['reason'],
        ];
        $current['subItems'] = $items;

        $this->sensitive->persistSensitive($transaction, $household, $current);
        $transaction->save();

        return response()->json($this->sensitive->formatApi($transaction->load('items'), $household));
    }

    public function deleteItem(Request $request, $txId, $itemId)
    {
        $household = $this->household($request);
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

        return response()->json($this->sensitive->formatApi($transaction->load('items'), $household));
    }

    public function show(Request $request, $id)
    {
        $household = $this->household($request);
        $transaction = Transaction::where('household_id', $household->id)
            ->with('items')
            ->findOrFail($id);

        return response()->json($this->sensitive->formatApi($transaction, $household));
    }

    public function cloneMonth(Request $request)
    {
        $household = $this->household($request);
        $targetMonth = (int) $request->month;
        $targetYear = (int) $request->year;
        $userId = $request->user()->id;

        $prevMonth = $targetMonth - 1;
        $prevYear = $targetYear;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }

        $prevMonthStr = $prevYear.'-'.str_pad((string) $prevMonth, 2, '0', STR_PAD_LEFT);
        $targetMonthStr = $targetYear.'-'.str_pad((string) $targetMonth, 2, '0', STR_PAD_LEFT);

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

        return response()->json(['message' => 'Hónap teljes tartalma átmásolva!']);
    }
}
