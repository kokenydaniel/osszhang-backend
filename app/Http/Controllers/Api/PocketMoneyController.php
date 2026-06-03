<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PocketMoneyService;
use Illuminate\Http\Request;

class PocketMoneyController extends Controller
{
    public function __construct(private readonly PocketMoneyService $pocketMoney) {}

    public function index(Request $request)
    {
        $year = $request->filled('year') ? (int) $request->query('year') : null;
        $month = $request->filled('month') ? (int) $request->query('month') : null;

        return response()->json(
            $this->pocketMoney->listForUser($request->user(), $year, $month),
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'memberLabel' => 'required|string|max:100',
            'memberUserId' => 'nullable|integer|exists:users,id',
            'entryType' => 'required|in:allowance,expense,adjustment',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'sometimes|string|max:8',
            'entryDate' => 'required|date',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->pocketMoney->create($request->user(), $validated),
            201,
        );
    }

    public function update(Request $request, int $pocket_money)
    {
        $validated = $request->validate([
            'memberLabel' => 'sometimes|string|max:100',
            'memberUserId' => 'nullable|integer|exists:users,id',
            'entryType' => 'sometimes|in:allowance,expense,adjustment',
            'amount' => 'sometimes|numeric|min:0.01',
            'currency' => 'sometimes|string|max:8',
            'entryDate' => 'sometimes|date',
            'note' => 'nullable|string|max:500',
        ]);

        return response()->json(
            $this->pocketMoney->update($request->user(), $pocket_money, $validated),
        );
    }

    public function destroy(Request $request, int $pocket_money)
    {
        $this->pocketMoney->delete($request->user(), $pocket_money);

        return response()->json(null, 204);
    }

    public function applyInterest(Request $request)
    {
        $validated = $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        return response()->json(
            $this->pocketMoney->applyMonthInterest(
                $request->user(),
                (int) $validated['year'],
                (int) $validated['month'],
            ),
        );
    }
}
