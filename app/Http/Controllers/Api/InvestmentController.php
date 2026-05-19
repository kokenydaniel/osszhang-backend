<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Investment;
use Illuminate\Http\Request;

class InvestmentController extends Controller {
    private function formatInvestment(Investment $i): array {
        return [
            'id' => $i->id,
            'name' => $i->name,
            'type' => $i->type,
            'principalAmount' => (float)$i->principal_amount,
            'annualInterestRate' => (float)$i->annual_interest_rate,
            'purchaseDate' => $i->purchase_date->toDateString(),
            'maturityDate' => $i->maturity_date ? $i->maturity_date->toDateString() : null,
            'owner' => $i->owner,
            'countInSavings' => (bool)$i->count_in_savings,
            'currentValue' => $i->current_value ? (float)$i->current_value : null,
            'maturityAmount' => $i->maturity_amount ? (float)$i->maturity_amount : null,
            'nextPayoutAmount' => $i->next_payout_amount ? (float)$i->next_payout_amount : null,
            'nextPayoutDate' => $i->next_payout_date ? $i->next_payout_date->toDateString() : null
        ];
    }

    public function index(Request $request) {
        return response()->json(
            Investment::where('household_id', $request->user()->household_id)
                ->get()
                ->map(fn($i) => $this->formatInvestment($i))
        );
    }

    public function store(Request $request) {
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
            'nextPayoutDate' => 'nullable|date'
        ]);
        $i = Investment::create([
            'household_id' => $request->user()->household_id,
            'name' => $v['name'],
            'type' => $v['type'] ?? 'bond',
            'principal_amount' => $v['principalAmount'],
            'annual_interest_rate' => $v['annualInterestRate'],
            'purchase_date' => $v['purchaseDate'],
            'maturity_date' => $v['maturityDate'] ?? null,
            'owner' => $v['owner'] ?? 'Közös',
            'count_in_savings' => $v['countInSavings'] ?? true,
            'current_value' => $v['currentValue'] ?? null,
            'maturity_amount' => $v['maturityAmount'] ?? null,
            'next_payout_amount' => $v['nextPayoutAmount'] ?? null,
            'next_payout_date' => $v['nextPayoutDate'] ?? null
        ]);
        return response()->json($this->formatInvestment($i), 201);
    }

    public function update(Request $request, $id) {
        $i = Investment::where('household_id', $request->user()->household_id)->findOrFail($id);
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
            'nextPayoutDate' => 'nullable|date'
        ]);
        $data = [];
        if (array_key_exists('name', $v)) $data['name'] = $v['name'];
        if (array_key_exists('type', $v)) $data['type'] = $v['type'];
        if (array_key_exists('principalAmount', $v)) $data['principal_amount'] = $v['principalAmount'];
        if (array_key_exists('annualInterestRate', $v)) $data['annual_interest_rate'] = $v['annualInterestRate'];
        if (array_key_exists('purchaseDate', $v)) $data['purchase_date'] = $v['purchaseDate'];
        if (array_key_exists('maturityDate', $v)) $data['maturity_date'] = $v['maturityDate'];
        if (array_key_exists('owner', $v)) $data['owner'] = $v['owner'];
        if (array_key_exists('countInSavings', $v)) $data['count_in_savings'] = $v['countInSavings'];
        if (array_key_exists('currentValue', $v)) $data['current_value'] = $v['currentValue'];
        if (array_key_exists('maturityAmount', $v)) $data['maturity_amount'] = $v['maturityAmount'];
        if (array_key_exists('nextPayoutAmount', $v)) $data['next_payout_amount'] = $v['nextPayoutAmount'];
        if (array_key_exists('nextPayoutDate', $v)) $data['next_payout_date'] = $v['nextPayoutDate'];
        $i->update($data);
        return response()->json($this->formatInvestment($i));
    }

    public function destroy(Request $request, $id) {
        Investment::where('household_id', $request->user()->household_id)->findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
