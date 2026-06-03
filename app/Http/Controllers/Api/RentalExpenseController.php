<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RentalService;
use Illuminate\Http\Request;

class RentalExpenseController extends Controller
{
    public function __construct(private readonly RentalService $rental) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rental_property_id' => 'required_without:rentalPropertyId|integer|exists:rental_properties,id',
            'rentalPropertyId' => 'required_without:rental_property_id|integer|exists:rental_properties,id',
            'expense_type' => 'required_without:expenseType|string|max:32',
            'expenseType' => 'required_without:expense_type|string|max:32',
            'amount' => 'required|numeric|min:0',
            'currency' => 'sometimes|string|max:8',
            'expense_date' => 'required_without:expenseDate|date',
            'expenseDate' => 'required_without:expense_date|date',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json($this->rental->createExpense($request->user(), $validated), 201);
    }

    public function update(Request $request, int $rental_expense)
    {
        $validated = $request->validate([
            'expense_type' => 'sometimes|string|max:32',
            'expenseType' => 'sometimes|string|max:32',
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:8',
            'expense_date' => 'sometimes|date',
            'expenseDate' => 'sometimes|date',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json($this->rental->updateExpense($request->user(), $rental_expense, $validated));
    }

    public function destroy(Request $request, int $rental_expense)
    {
        $this->rental->deleteExpense($request->user(), $rental_expense);

        return response()->json(null, 204);
    }
}
