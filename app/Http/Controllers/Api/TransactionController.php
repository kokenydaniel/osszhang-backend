<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\LedgerEntry;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    private function formatTransaction($t)
    {
        return [
            'id' => $t->id,
            'type' => $t->type,
            'description' => $t->description,
            'category' => $t->category,
            'amount' => (float) $t->amount,
            'dueDate' => $t->due_date,
            'paidDate' => $t->paid_date,
            'isBudget' => (bool) $t->is_budget,
            'isReserve' => (bool) $t->is_reserve,
            'subItems' => $t->items->map(fn($i) => [
                'id' => $i->id,
                'date' => $i->date,
                'amount' => (float)$i->amount,
                'reason' => $i->reason
            ])
        ];
    }

    public function index(Request $request)
    {
        $transactions = Transaction::where('household_id', $request->user()->household_id)
            ->with('items')
            ->orderBy('due_date', 'desc')
            ->get();

        return response()->json($transactions->map(fn($t) => $this->formatTransaction($t)));
    }

    public function store(Request $request)
    {
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

        $transaction = Transaction::create([
            'household_id' => $request->user()->household_id,
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'amount' => $validated['amount'],
            'due_date' => $validated['dueDate'],
            'paid_date' => $validated['paidDate'] ?? null,
            'is_budget' => $validated['isBudget'] ?? false,
            'is_reserve' => $validated['isReserve'] ?? false,
        ]);

        return response()->json($this->formatTransaction($transaction->load('items')), 201);
    }

    public function update(Request $request, $id)
    {
        $transaction = Transaction::where('household_id', $request->user()->household_id)
            ->with('items')
            ->findOrFail($id);

        $data = [];
        if ($request->has('description')) $data['description'] = $request->description;
        if ($request->has('type')) $data['type'] = $request->type;
        if ($request->has('amount')) $data['amount'] = $request->amount;
        if ($request->has('category')) $data['category'] = $request->category;
        if ($request->has('dueDate')) $data['due_date'] = $request->dueDate;
        if ($request->has('paidDate')) $data['paid_date'] = $request->paidDate;
        if ($request->has('isBudget')) $data['is_budget'] = $request->isBudget;
        if ($request->has('isReserve')) $data['is_reserve'] = $request->isReserve;

        $transaction->update($data);

        return response()->json($this->formatTransaction($transaction));
    }

    public function destroy($id)
    {
        Transaction::where('household_id', auth()->user()->household_id)->findOrFail($id)->delete();
        return response()->json(null, 204);
    }

    public function addItem(Request $request, $id)
    {
        $transaction = Transaction::where('household_id', $request->user()->household_id)->findOrFail($id);
        
        $v = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string',
            'date' => 'required|date'
        ]);

        $transaction->items()->create([
            'amount' => $v['amount'],
            'reason' => $v['reason'],
            'date' => $v['date']
        ]);

        return response()->json($this->formatTransaction($transaction->load('items')));
    }

    public function deleteItem(Request $request, $txId, $itemId)
    {
        $transaction = Transaction::where('household_id', $request->user()->household_id)->findOrFail($txId);
        $item = LedgerEntry::where('transaction_id', $transaction->id)->findOrFail($itemId);
        $item->delete();

        return response()->json($this->formatTransaction($transaction->load('items')));
    }

    public function show(Request $request, $id)
    {
        $transaction = Transaction::where('household_id', $request->user()->household_id)
            ->with('items')
            ->findOrFail($id);
        return response()->json($this->formatTransaction($transaction));
    }

    public function cloneMonth(Request $request)
    {
        $targetMonth = (int)$request->month;
        $targetYear = (int)$request->year;
        $householdId = $request->user()->household_id;
        $userId = $request->user()->id;

        // Calculate previous month
        $prevMonth = $targetMonth - 1;
        $prevYear = $targetYear;
        if ($prevMonth === 0) {
            $prevMonth = 12;
            $prevYear--;
        }

        $prevMonthStr = $prevYear . '-' . str_pad($prevMonth, 2, '0', STR_PAD_LEFT);
        $targetMonthStr = $targetYear . '-' . str_pad($targetMonth, 2, '0', STR_PAD_LEFT);

        // Clone ALL non-bill transactions from previous month
        $toClone = Transaction::where('household_id', $householdId)
            ->where('due_date', 'like', $prevMonthStr . '%')
            ->get();

        foreach ($toClone as $tx) {
            $newDate = str_replace($prevMonthStr, $targetMonthStr, $tx->due_date);
            
            Transaction::firstOrCreate(
                [
                    'household_id' => $householdId,
                    'description' => $tx->description,
                    'due_date' => $newDate,
                ],
                [
                    'user_id' => $userId,
                    'type' => $tx->type,
                    'category' => $tx->category,
                    'amount' => $tx->amount,
                    'is_budget' => $tx->is_budget,
                ]
            );
        }

        return response()->json(['message' => 'Hónap teljes tartalma átmásolva!']);
    }
}
