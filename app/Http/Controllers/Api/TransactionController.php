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
        return response()->json(
            $this->budgetService->listForHousehold($request->user()->household),
        );
    }

    public function store(StoreTransactionRequest $request)
    {
        return response()->json(
            $this->budgetService->create(
                $request->user()->household,
                $request->user(),
                $request->validated(),
            ),
            201,
        );
    }

    public function update(Request $request, $id)
    {
        $input = collect(['description', 'type', 'amount', 'category', 'dueDate', 'paidDate', 'isBudget', 'isReserve'])
            ->filter(fn ($key) => $request->has($key))
            ->mapWithKeys(fn ($key) => [$key => $request->input($key)])
            ->all();

        return response()->json(
            $this->budgetService->update($request->user()->household, $id, $input),
        );
    }

    public function destroy(Request $request, $id)
    {
        $this->budgetService->delete($request->user()->household, $id);

        return response()->json(null, 204);
    }

    public function addItem(AddTransactionItemRequest $request, $id)
    {
        return response()->json(
            $this->budgetService->addItem($request->user()->household, $id, $request->validated()),
        );
    }

    public function deleteItem(Request $request, $txId, $itemId)
    {
        return response()->json(
            $this->budgetService->deleteItem($request->user()->household, $txId, $itemId),
        );
    }

    public function show(Request $request, $id)
    {
        return response()->json(
            $this->budgetService->show($request->user()->household, $id),
        );
    }

    public function cloneMonth(Request $request)
    {
        return response()->json(
            $this->budgetService->cloneMonth(
                $request->user()->household,
                $request->user()->id,
                (int) $request->month,
                (int) $request->year,
            ),
        );
    }
}
