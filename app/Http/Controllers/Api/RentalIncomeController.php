<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RentalService;
use Illuminate\Http\Request;

class RentalIncomeController extends Controller
{
    public function __construct(private readonly RentalService $rental) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'rental_property_id' => 'required_without:rentalPropertyId|integer|exists:rental_properties,id',
            'rentalPropertyId' => 'required_without:rental_property_id|integer|exists:rental_properties,id',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|string|max:8',
            'period_year' => 'required_without:periodYear|integer|min:2000|max:2100',
            'periodYear' => 'required_without:period_year|integer|min:2000|max:2100',
            'period_month' => 'required_without:periodMonth|integer|min:1|max:12',
            'periodMonth' => 'required_without:period_month|integer|min:1|max:12',
            'paid_date' => 'nullable|date',
            'paidDate' => 'nullable|date',
            'due_date' => 'nullable|date',
            'dueDate' => 'nullable|date',
            'rent_amount' => 'nullable|numeric|min:0',
            'rentAmount' => 'nullable|numeric|min:0',
            'common_cost_amount' => 'nullable|numeric|min:0',
            'commonCostAmount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json($this->rental->createIncome($request->user(), $validated), 201);
    }

    public function update(Request $request, int $rental_income_entry)
    {
        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'currency' => 'sometimes|string|max:8',
            'period_year' => 'sometimes|integer|min:2000|max:2100',
            'periodYear' => 'sometimes|integer|min:2000|max:2100',
            'period_month' => 'sometimes|integer|min:1|max:12',
            'periodMonth' => 'sometimes|integer|min:1|max:12',
            'paid_date' => 'nullable|date',
            'paidDate' => 'nullable|date',
            'due_date' => 'nullable|date',
            'dueDate' => 'nullable|date',
            'rent_amount' => 'nullable|numeric|min:0',
            'rentAmount' => 'nullable|numeric|min:0',
            'common_cost_amount' => 'nullable|numeric|min:0',
            'commonCostAmount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json($this->rental->updateIncome($request->user(), $rental_income_entry, $validated));
    }

    public function destroy(Request $request, int $rental_income_entry)
    {
        $this->rental->deleteIncome($request->user(), $rental_income_entry);

        return response()->json(null, 204);
    }
}
