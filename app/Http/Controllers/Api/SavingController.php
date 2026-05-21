<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SavingService;
use Illuminate\Http\Request;

class SavingController extends Controller
{
    public function __construct(private readonly SavingService $savingService) {}

    public function index(Request $request)
    {
        return response()->json($this->savingService->listForHousehold($request->user()->household));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'institution' => 'required|string',
            'currency' => 'required|string',
            'owner' => 'sometimes|string',
            'count_in_savings' => 'sometimes|boolean',
        ]);

        return response()->json($this->savingService->create($request->user()->household, $v), 201);
    }

    public function addEntry(Request $request, $id)
    {
        $v = $request->validate([
            'amount' => 'required|numeric',
            'reason' => 'required|string',
            'date' => 'required|date',
        ]);

        return response()->json($this->savingService->addEntry($request->user()->household, $id, $v));
    }

    public function updateEntry(Request $request, $savingId, $entryId)
    {
        $v = $request->validate([
            'amount' => 'sometimes|numeric',
            'reason' => 'sometimes|string',
            'date' => 'sometimes|date',
        ]);

        return response()->json(
            $this->savingService->updateEntry($request->user()->household, $savingId, $entryId, $v),
        );
    }

    public function deleteEntry(Request $request, $savingId, $entryId)
    {
        return response()->json(
            $this->savingService->deleteEntry($request->user()->household, $savingId, $entryId),
        );
    }

    public function update(Request $request, $id)
    {
        $v = $request->validate([
            'institution' => 'sometimes|string',
            'currency' => 'sometimes|string',
            'owner' => 'sometimes|string',
            'count_in_savings' => 'sometimes|boolean',
        ]);

        return response()->json($this->savingService->update($request->user()->household, $id, $v));
    }

    public function destroy($id)
    {
        $this->savingService->delete(auth()->user()->household_id, $id);

        return response()->json(null, 204);
    }
}
