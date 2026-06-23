<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InsurancePolicyService;
use Illuminate\Http\Request;

class InsuranceController extends Controller
{
    public function __construct(private readonly InsurancePolicyService $insurance) {}

    public function index(Request $request)
    {
        return response()->json($this->insurance->listForUser($request->user()));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePolicy($request);

        return response()->json($this->insurance->create($request->user(), $validated), 201);
    }

    public function update(Request $request, int $insurance_policy)
    {
        $validated = $this->validatePolicy($request, true);

        return response()->json(
            $this->insurance->update($request->user(), $insurance_policy, $validated),
        );
    }

    public function destroy(Request $request, int $insurance_policy)
    {
        return response()->json(
            $this->insurance->delete($request->user(), $insurance_policy),
        );
    }

    private function validatePolicy(Request $request, bool $partial = false): array
    {
        $rules = [
            'name' => ($partial ? 'sometimes|' : '').'required|string|max:150',
            'insurer' => 'nullable|string|max:150',
            'policyKind' => 'sometimes|in:general,life_investment',
            'policy_kind' => 'sometimes|in:general,life_investment',
            'annualPremium' => 'nullable|numeric|min:0',
            'annual_premium' => 'nullable|numeric|min:0',
            'fundValue' => 'nullable|numeric|min:0',
            'fund_value' => 'nullable|numeric|min:0',
            'premiumFree' => 'sometimes|boolean',
            'premium_free' => 'sometimes|boolean',
            'paymentFrequency' => 'sometimes|in:monthly,quarterly,semiannual,annual',
            'payment_frequency' => 'sometimes|in:monthly,quarterly,semiannual,annual',
            'paymentAmount' => 'nullable|numeric|min:0',
            'payment_amount' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|string|max:8',
            'renewalDate' => 'nullable|date',
            'renewal_date' => 'nullable|date',
            'coveredUntil' => 'nullable|date',
            'covered_until' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
            'isActive' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'budgetSyncEnabled' => 'sometimes|boolean',
            'budget_sync_enabled' => 'sometimes|boolean',
            'budgetStartYear' => 'nullable|integer|min:2000|max:2100',
            'budget_start_year' => 'nullable|integer|min:2000|max:2100',
            'budgetStartMonth' => 'nullable|integer|min:1|max:12',
            'budget_start_month' => 'nullable|integer|min:1|max:12',
            'budgetDueDay' => 'nullable|integer|min:1|max:31',
            'budget_due_day' => 'nullable|integer|min:1|max:31',
            'paidBudgetPeriods' => 'sometimes|array',
            'paidBudgetPeriods.*' => 'string|regex:/^\d{4}-\d{2}$/',
            'paid_budget_periods' => 'sometimes|array',
            'paid_budget_periods.*' => 'string|regex:/^\d{4}-\d{2}$/',
        ];

        return $request->validate($rules);
    }
}
