<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Debt;
use Illuminate\Http\Request;
class DebtController extends Controller {
    private function formatDebt(Debt $d): array {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'targetAmount' => (float)$d->target_amount,
            'paidAmount' => (float)$d->paid_amount,
            'annualInterestRate' => $d->annual_interest_rate !== null ? (float)$d->annual_interest_rate : null,
            'minimumPayment' => $d->minimum_payment !== null ? (float)$d->minimum_payment : null,
            'dueDay' => $d->due_day !== null ? (int)$d->due_day : null,
            'status' => $d->status
        ];
    }

    public function index(Request $request) {
        return response()->json(
            Debt::where('household_id', $request->user()->household_id)
                ->get()
                ->map(fn($d) => $this->formatDebt($d))
        );
    }
    public function store(Request $request) {
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'targetAmount' => 'required|numeric|min:0',
            'paidAmount' => 'nullable|numeric|min:0',
            'annualInterestRate' => 'nullable|numeric|min:0|max:100',
            'minimumPayment' => 'nullable|numeric|min:0',
            'dueDay' => 'nullable|integer|min:1|max:31',
            'status' => 'nullable|string'
        ]);
        $d = Debt::create([
            'household_id' => $request->user()->household_id,
            'name' => $v['name'],
            'target_amount' => $v['targetAmount'],
            'paid_amount' => $v['paidAmount'] ?? 0,
            'annual_interest_rate' => $v['annualInterestRate'] ?? null,
            'minimum_payment' => $v['minimumPayment'] ?? null,
            'due_day' => $v['dueDay'] ?? null,
            'status' => $v['status'] ?? 'Még fizetendő'
        ]);
        return response()->json($this->formatDebt($d), 201);
    }
    public function update(Request $request, $id) {
        $d = Debt::where('household_id', $request->user()->household_id)->findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|string|max:255',
            'targetAmount' => 'sometimes|numeric|min:0',
            'paidAmount' => 'sometimes|numeric|min:0',
            'annualInterestRate' => 'nullable|numeric|min:0|max:100',
            'minimumPayment' => 'nullable|numeric|min:0',
            'dueDay' => 'nullable|integer|min:1|max:31',
            'status' => 'sometimes|string'
        ]);
        $data = [];
        if (array_key_exists('name', $v)) $data['name'] = $v['name'];
        if (array_key_exists('targetAmount', $v)) $data['target_amount'] = $v['targetAmount'];
        if (array_key_exists('paidAmount', $v)) $data['paid_amount'] = $v['paidAmount'];
        if (array_key_exists('annualInterestRate', $v)) $data['annual_interest_rate'] = $v['annualInterestRate'];
        if (array_key_exists('minimumPayment', $v)) $data['minimum_payment'] = $v['minimumPayment'];
        if (array_key_exists('dueDay', $v)) $data['due_day'] = $v['dueDay'];
        if (array_key_exists('status', $v)) $data['status'] = $v['status'];
        $d->update($data);
        return response()->json($this->formatDebt($d));
    }

    public function destroy(Request $request, $id) {
        Debt::where('household_id', $request->user()->household_id)->findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
