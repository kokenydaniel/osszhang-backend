<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Utility\SettleMonthRequest;
use App\Http\Requests\Utility\StoreUtilityRequest;
use App\Services\UtilityService;
use Illuminate\Http\Request;

class UtilityController extends Controller
{
    public function __construct(private readonly UtilityService $utilityService) {}

    public function index(Request $request)
    {
        $household = $request->user()->household()->with('utilitySplitPartner')->first();

        return response()->json($this->utilityService->payloadForHousehold($household->id, $household));
    }

    public function store(StoreUtilityRequest $request)
    {
        try {
            return response()->json(
                $this->utilityService->create($request->user()->household, $request->validated()),
                201,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(Request $request, $id)
    {
        $input = collect(['type', 'total', 'splitRule', 'paidBy', 'dueDate', 'paidDate'])
            ->filter(fn ($key) => $request->has($key))
            ->mapWithKeys(fn ($key) => [$key => $request->input($key)])
            ->all();

        try {
            return response()->json(
                $this->utilityService->update($request->user()->household, $id, $input),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, $id)
    {
        $this->utilityService->delete($request->user()->household_id, $id);

        return response()->json(null, 204);
    }

    public function settleMonth(SettleMonthRequest $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Csak adminisztrátor rögzíthet elszámolást.'], 403);
        }

        $household = $request->user()->household()->with('utilitySplitPartner')->first();

        try {
            return response()->json(
                $this->utilityService->settleMonth($household, $request->user(), $request->validated()),
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function unsettleMonth(SettleMonthRequest $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Csak adminisztrátor vonhatja vissza az elszámolást.'], 403);
        }

        try {
            return response()->json(
                $this->utilityService->unsettleMonth($request->user()->household, $request->validated()),
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function cloneMonth(Request $request)
    {
        return response()->json(
            $this->utilityService->cloneMonth(
                $request->user()->household,
                (int) $request->month,
                (int) $request->year,
            ),
        );
    }
}
