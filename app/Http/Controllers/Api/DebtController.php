<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Debt;
use App\Services\EncryptedRecordService;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    public function __construct(
        private readonly EncryptedRecordService $crypto,
    ) {}

    public function index(Request $request)
    {
        $household = $request->user()->household;

        return response()->json(
            Debt::where('household_id', $household->id)
                ->get()
                ->map(fn ($d) => $this->crypto->formatDebt($d, $household))
        );
    }

    public function store(Request $request)
    {
        $household = $request->user()->household;
        $v = $request->validate([
            'name' => 'required|string|max:255',
            'targetAmount' => 'required|numeric|min:0',
            'paidAmount' => 'nullable|numeric|min:0',
            'annualInterestRate' => 'nullable|numeric|min:0|max:100',
            'minimumPayment' => 'nullable|numeric|min:0',
            'dueDay' => 'nullable|integer|min:1|max:31',
            'status' => 'nullable|string',
        ]);

        $d = new Debt(['household_id' => $household->id]);
        $this->crypto->persistDebt($d, $household, [
            'name' => $v['name'],
            'target_amount' => (float) $v['targetAmount'],
            'paid_amount' => (float) ($v['paidAmount'] ?? 0),
            'annual_interest_rate' => $v['annualInterestRate'] ?? null,
            'minimum_payment' => $v['minimumPayment'] ?? null,
            'due_day' => $v['dueDay'] ?? null,
            'status' => $v['status'] ?? 'Még fizetendő',
        ]);
        $d->save();

        return response()->json($this->crypto->formatDebt($d, $household), 201);
    }

    public function update(Request $request, $id)
    {
        $household = $request->user()->household;
        $d = Debt::where('household_id', $household->id)->findOrFail($id);
        $v = $request->validate([
            'name' => 'sometimes|string|max:255',
            'targetAmount' => 'sometimes|numeric|min:0',
            'paidAmount' => 'sometimes|numeric|min:0',
            'annualInterestRate' => 'nullable|numeric|min:0|max:100',
            'minimumPayment' => 'nullable|numeric|min:0',
            'dueDay' => 'nullable|integer|min:1|max:31',
            'status' => 'sometimes|string',
        ]);

        $sensitive = $this->crypto->debtResolved($d, $household);
        if (array_key_exists('name', $v)) {
            $sensitive['name'] = $v['name'];
        }
        if (array_key_exists('targetAmount', $v)) {
            $sensitive['target_amount'] = (float) $v['targetAmount'];
        }
        if (array_key_exists('paidAmount', $v)) {
            $sensitive['paid_amount'] = (float) $v['paidAmount'];
        }
        if (array_key_exists('annualInterestRate', $v)) {
            $sensitive['annual_interest_rate'] = $v['annualInterestRate'];
        }
        if (array_key_exists('minimumPayment', $v)) {
            $sensitive['minimum_payment'] = $v['minimumPayment'];
        }
        if (array_key_exists('dueDay', $v)) {
            $sensitive['due_day'] = $v['dueDay'];
        }
        if (array_key_exists('status', $v)) {
            $sensitive['status'] = $v['status'];
        }

        $this->crypto->persistDebt($d, $household, $sensitive);
        $d->save();

        return response()->json($this->crypto->formatDebt($d, $household));
    }

    public function destroy(Request $request, $id)
    {
        Debt::where('household_id', $request->user()->household_id)->findOrFail($id)->delete();

        return response()->json(null, 204);
    }
}
