<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Budget\AddTransactionItemRequest;
use App\Http\Requests\Budget\StoreTransactionRequest;
use App\Services\BudgetService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(private readonly BudgetService $budgetService) {}

    public function index(Request $request)
    {
        $walletId = $request->filled('walletId') ? (int) $request->query('walletId') : null;
        $month = $request->filled('month') ? (int) $request->query('month') : null;
        $year = $request->filled('year') ? (int) $request->query('year') : null;

        if ($month !== null && $year !== null) {
            return response()->json(
                $this->budgetService->listForUser($request->user(), $walletId, $month, $year),
            );
        }

        return response()->json([
            'transactions' => $this->budgetService->listTransactionsForUser($request->user(), $walletId),
            'goalRows' => [],
        ]);
    }

    public function goalRows(Request $request)
    {
        $walletId = $request->filled('walletId') ? (int) $request->query('walletId') : null;

        return response()->json(
            $this->budgetService->goalRowsForMonth(
                $request->user(),
                $walletId,
                (int) $request->query('month'),
                (int) $request->query('year'),
            ),
        );
    }

    public function store(StoreTransactionRequest $request)
    {
        return response()->json(
            $this->budgetService->create($request->user(), $request->validated()),
            201,
        );
    }

    public function update(Request $request, $id)
    {
        $input = collect(['description', 'type', 'amount', 'category', 'dueDate', 'paidDate', 'isBudget', 'isReserve', 'walletId'])
            ->filter(fn ($key) => $request->has($key))
            ->mapWithKeys(fn ($key) => [$key => $request->input($key)])
            ->all();

        return response()->json(
            $this->budgetService->update($request->user(), $id, $input),
        );
    }

    public function destroy(Request $request, $id)
    {
        $this->budgetService->delete($request->user(), $id);

        return response()->json(null, 204);
    }

    public function addItem(AddTransactionItemRequest $request, $id)
    {
        return response()->json(
            $this->budgetService->addItem($request->user(), $id, $request->validated()),
        );
    }

    public function updateItem(AddTransactionItemRequest $request, $txId, $itemId)
    {
        return response()->json(
            $this->budgetService->updateItem($request->user(), $txId, $itemId, $request->validated()),
        );
    }

    public function deleteItem(Request $request, $txId, $itemId)
    {
        return response()->json(
            $this->budgetService->deleteItem($request->user(), $txId, $itemId),
        );
    }

    public function show(Request $request, $id)
    {
        return response()->json(
            $this->budgetService->show($request->user(), $id),
        );
    }

    public function cloneMonth(Request $request)
    {
        $walletId = $request->filled('walletId') ? (int) $request->input('walletId') : null;

        return response()->json(
            $this->budgetService->cloneMonth(
                $request->user(),
                (int) $request->month,
                (int) $request->year,
                $walletId,
            ),
        );
    }
}
