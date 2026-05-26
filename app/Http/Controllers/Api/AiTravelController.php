<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AIFinance\TravelPlanRequest;
use App\Services\AIFinanceService;

class AiTravelController extends Controller
{
    public function __construct(private readonly AIFinanceService $aiFinanceService) {}

    public function plan(TravelPlanRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->travelPlan($request->validated()),
        );
    }
}
