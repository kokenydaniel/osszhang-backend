<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Investment;
use App\Services\EncryptedRecordService;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function index(Request $request)
    {
        $household = $request->user()->household;

        return response()->json(
            Investment::where('household_id', $household->id)
                ->get()
                ->map(fn ($i) => $this->crypto->formatInvestment($i, $household))
        );
    }

    public function store(Request $request)
    {
        $household = $request->user()->household;
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|string',
            'principalAmount' => 'required|numeric|min:0',
            'annualInterestRate' => 'required|numeric|min:0|max:100',
            'purchaseDate' => 'required|date',
            'maturityDate' => 'nullable|date',
            'owner' => 'nullable|string',
            'countInSavings' => 'nullable|boolean',
            'currentValue' => 'nullable|numeric|min:0',
            'maturityAmount' => 'nullable|numeric|min:0',
            'nextPayoutAmount' => 'nullable|numeric|min:0',
            'nextPayoutDate' => 'nullable|date',
        ]);

        $i = new Investment([
            'household_id' => $household->id,
            'purchase_date' => $v['purchaseDate'],
            'maturity_date' => $v['maturityDate'] ?? null,
            'count_in_savings' => $v['countInSavings'] ?? true,
            'next_payout_date' => $v['nextPayoutDate'] ?? null,
        ]);
        $this->crypto->persistInvestment($i, $household, [
            'name' => $v['name'],
            'type' => $v['type'] ?? 'bond',
            'principal_amount' => (float) $v['principalAmount'],
            'annual_interest_rate' => (float) $v['annualInterestRate'],
            'owner' => $v['owner'] ?? 'Közös',
            'current_value' => $v['currentValue'] ?? null,
            'maturity_amount' => $v['maturityAmount'] ?? null,
            'next_payout_amount' => $v['nextPayoutAmount'] ?? null,
        ]);
        $i->save();

        return response()->json($this->crypto->formatInvestment($i, $household), 201);
    }

    public function update(Request $request, $id)
    {
        $household = $request->user()->household;
        $i = Investment::where('household_id', $household->id)->findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string',
            'principalAmount' => 'sometimes|numeric|min:0',
            'annualInterestRate' => 'sometimes|numeric|min:0|max:100',
            'purchaseDate' => 'sometimes|date',
            'maturityDate' => 'nullable|date',
            'owner' => 'nullable|string',
            'countInSavings' => 'sometimes|boolean',
            'currentValue' => 'nullable|numeric|min:0',
            'maturityAmount' => 'nullable|numeric|min:0',
            'nextPayoutAmount' => 'nullable|numeric|min:0',
            'nextPayoutDate' => 'nullable|date',
        ]);

        $sensitive = $this->crypto->investmentResolved($i, $household);
        if (array_key_exists('name', $v)) {
            $sensitive['name'] = $v['name'];
        }
        if (array_key_exists('type', $v)) {
            $sensitive['type'] = $v['type'];
        }
        if (array_key_exists('principalAmount', $v)) {
            $sensitive['principal_amount'] = (float) $v['principalAmount'];
        }
        if (array_key_exists('annualInterestRate', $v)) {
            $sensitive['annual_interest_rate'] = (float) $v['annualInterestRate'];
        }
        if (array_key_exists('owner', $v)) {
            $sensitive['owner'] = $v['owner'];
        }
        if (array_key_exists('currentValue', $v)) {
            $sensitive['current_value'] = $v['currentValue'];
        }
        if (array_key_exists('maturityAmount', $v)) {
            $sensitive['maturity_amount'] = $v['maturityAmount'];
        }
        if (array_key_exists('nextPayoutAmount', $v)) {
            $sensitive['next_payout_amount'] = $v['nextPayoutAmount'];
        }
        if (array_key_exists('purchaseDate', $v)) {
            $i->purchase_date = $v['purchaseDate'];
        }
        if (array_key_exists('maturityDate', $v)) {
            $i->maturity_date = $v['maturityDate'];
        }
        if (array_key_exists('nextPayoutDate', $v)) {
            $i->next_payout_date = $v['nextPayoutDate'];
        }
        if (array_key_exists('countInSavings', $v)) {
            $i->count_in_savings = $v['countInSavings'];
        }

        $this->crypto->persistInvestment($i, $household, $sensitive);
        $i->save();

        return response()->json($this->crypto->formatInvestment($i, $household));
    }

    public function destroy(Request $request, $id)
    {
        Investment::where('household_id', $request->user()->household_id)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
