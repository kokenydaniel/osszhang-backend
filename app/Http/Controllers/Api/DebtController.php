<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DebtService;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function __construct(private readonly DebtService $debtService) {}

    public function index(Request $request)
    {
        return response()->json($this->debtService->listForHousehold($request->user()->household));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'targetAmount' => 'required|numeric|min:0',
            'paidAmount' => 'nullable|numeric|min:0',
            'annualInterestRate' => 'nullable|numeric|min:0|max:100',
            'minimumPayment' => 'nullable|numeric|min:0',
            'dueDay' => 'nullable|integer|min:1|max:31',
            'status' => 'nullable|string',
        ]);

        return response()->json($this->debtService->create($request->user()->household, $v), 201);
    }

    public function update(Request $request, $id)
    {
        $v = $request->validate([
            'name' => 'sometimes|string|max:255',
            'targetAmount' => 'sometimes|numeric|min:0',
            'paidAmount' => 'sometimes|numeric|min:0',
            'annualInterestRate' => 'nullable|numeric|min:0|max:100',
            'minimumPayment' => 'nullable|numeric|min:0',
            'dueDay' => 'nullable|integer|min:1|max:31',
            'status' => 'sometimes|string',
        ]);

        return response()->json($this->debtService->update($request->user()->household, $id, $v));
    }

    public function destroy(Request $request, $id)
    {
        $this->debtService->delete($request->user()->household_id, $id);

        return response()->json(null, 204);
    }
}
