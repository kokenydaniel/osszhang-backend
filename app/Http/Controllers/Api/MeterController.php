<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MeterService;
use Illuminate\Http\Request;

class MeterController extends Controller
{
    public function __construct(private readonly MeterService $meterService) {}

    public function index(Request $request)
    {
        return response()->json($this->meterService->listForHousehold($request->user()->household));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'name' => 'required|string',
            'unit' => 'required|string',
            'location' => 'nullable|string',
        ]);

        return response()->json($this->meterService->create($request->user()->household, $v), 201);
    }

    public function show(Request $request, $id)
    {
        return response()->json($this->meterService->show($request->user()->household, $id));
    }

    public function addReading(Request $request, $id)
    {
        $v = $request->validate([
            'value' => 'required|numeric',
            'date' => 'required|date',
        ]);

        return response()->json(
            $this->meterService->addReading($request->user()->household, $id, $v, $request),
        );
    }

    public function updateReading(Request $request, $meterId, $readingId)
    {
        $v = $request->validate([
            'value' => 'required|numeric',
            'date' => 'required|date',
        ]);

        return response()->json(
            $this->meterService->updateReading($request->user()->household, $meterId, $readingId, $v, $request),
        );
    }

    public function deleteReading(Request $request, $meterId, $readingId)
    {
        return response()->json(
            $this->meterService->deleteReading($request->user()->household, $meterId, $readingId),
        );
    }

    public function bulkDeleteReadings(Request $request, $meterId)
    {
        $v = $request->validate([
            'reading_ids' => 'required|array|min:1',
            'reading_ids.*' => 'integer',
        ]);

        return response()->json(
            $this->meterService->deleteReadingsBulk($request->user()->household, $meterId, $v['reading_ids']),
        );
    }

    public function destroy($id)
    {
        $this->meterService->delete(auth()->user()->household_id, $id);

        return response()->json(null, 204);
    }
}
