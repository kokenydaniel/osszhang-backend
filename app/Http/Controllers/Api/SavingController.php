<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Saving;
use App\Services\SavingService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SavingController extends Controller
{
    public function __construct(private readonly SavingService $savingService) {}

    public function index(Request $request)
    {
        $walletId = $request->filled('walletId') ? (int) $request->query('walletId') : null;

        return response()->json(
            $this->savingService->listForUser($request->user(), $walletId),
        );
    }

    public function store(Request $request)
    {
        $type = $request->input('type', Saving::TYPE_ACCOUNT);

        $rules = [
            'institution' => 'required|string',
            'type' => ['sometimes', Rule::in([Saving::TYPE_ACCOUNT, Saving::TYPE_GOAL])],
            'currency' => 'sometimes|string',
            'owner' => 'sometimes|string',
            'count_in_savings' => 'sometimes|boolean',
            'walletId' => 'sometimes|integer|exists:wallets,id',
        ];

        if ($type === Saving::TYPE_GOAL) {
            $rules['goal_amount'] = 'required|numeric|min:0.01';
            $rules['current_amount'] = 'sometimes|numeric|min:0';
            $rules['target_date'] = 'required|date|after_or_equal:today';
        }

        $v = $request->validate($rules);
        $v['type'] = $type;

        return response()->json(
            $this->savingService->create($request->user(), $v),
            201,
        );
    }

    public function addEntry(Request $request, $id)
    {
        $v = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);

        return response()->json(
            $this->savingService->addEntry($request->user(), $id, $v),
        );
    }

    public function updateEntry(Request $request, $savingId, $entryId)
    {
        $v = $request->validate([
            'amount' => 'sometimes|numeric',
            'reason' => 'sometimes|string',
            'date' => 'sometimes|date',
        ]);

        return response()->json(
            $this->savingService->updateEntry($request->user(), $savingId, $entryId, $v),
        );
    }

    public function deleteEntry(Request $request, $savingId, $entryId)
    {
        return response()->json(
            $this->savingService->deleteEntry($request->user(), $savingId, $entryId),
        );
    }

    public function upsertMonthlyContribution(Request $request, $id)
    {
        $v = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'amount' => 'required|numeric|min:0',
            'reason' => 'sometimes|string',
        ]);

        return response()->json(
            $this->savingService->upsertMonthlyActual(
                $request->user(),
                (int) $id,
                (int) $v['month'],
                (int) $v['year'],
                (float) $v['amount'],
                $v['reason'] ?? null,
            ),
        );
    }

    public function update(Request $request, $id)
    {
        $v = $request->validate([
            'institution' => 'sometimes|string',
            'currency' => 'sometimes|string',
            'owner' => 'sometimes|string',
            'count_in_savings' => 'sometimes|boolean',
            'goal_amount' => 'sometimes|numeric|min:0',
            'current_amount' => 'sometimes|numeric|min:0',
            'target_date' => 'sometimes|nullable|date',
            'type' => ['sometimes', Rule::in([Saving::TYPE_ACCOUNT, Saving::TYPE_GOAL])],
        ]);

        return response()->json(
            $this->savingService->update($request->user(), $id, $v),
        );
    }

    public function destroy(Request $request, $id)
    {
        $this->savingService->delete($request->user(), $id);

        return response()->json(null, 204);
    }
}
