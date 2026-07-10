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
        $walletId = $request->filled('walletId') ? (int) $request->query('walletId') : null;

        return response()->json(
            $this->debtService->listForUser($request->user(), $walletId),
        );
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
            'walletId' => 'sometimes|integer|exists:wallets,id',
            'budgetSyncEnabled' => 'sometimes|boolean',
            'budgetStartYear' => 'nullable|integer|min:2000|max:2100',
            'budgetStartMonth' => 'nullable|integer|min:1|max:12',
            'paidInstallmentMonths' => 'sometimes|array',
            'paidInstallmentMonths.*' => 'string|regex:/^\d{4}-\d{2}$/',
            'installmentPayments' => 'sometimes|array',
            'installmentPayments.*.period' => 'required_with:installmentPayments|string|regex:/^\d{4}-\d{2}$/',
            'installmentPayments.*.paidAt' => 'nullable|date_format:Y-m-d',
            'installmentPayments.*.amount' => 'required_with:installmentPayments|numeric|min:0',
            'installmentPayments.*.source' => 'required_with:installmentPayments|string|in:budget,debt_pay',
            'installmentPayments.*.note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->debtService->create($request->user(), $v),
            201,
        );
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
            'budgetSyncEnabled' => 'sometimes|boolean',
            'budgetStartYear' => 'nullable|integer|min:2000|max:2100',
            'budgetStartMonth' => 'nullable|integer|min:1|max:12',
            'paidInstallmentMonths' => 'sometimes|array',
            'paidInstallmentMonths.*' => 'string|regex:/^\d{4}-\d{2}$/',
            'installmentPayments' => 'sometimes|array',
            'installmentPayments.*.period' => 'required_with:installmentPayments|string|regex:/^\d{4}-\d{2}$/',
            'installmentPayments.*.paidAt' => 'nullable|date_format:Y-m-d',
            'installmentPayments.*.amount' => 'required_with:installmentPayments|numeric|min:0',
            'installmentPayments.*.source' => 'required_with:installmentPayments|string|in:budget,debt_pay',
            'installmentPayments.*.note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->debtService->update($request->user(), $id, $v),
        );
    }

    public function destroy(Request $request, $id)
    {
        $this->debtService->delete($request->user(), $id);

        return response()->json(null, 204);
    }
}
