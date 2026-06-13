<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AIFinance\TravelPlanRequest;
use App\Services\AIFinanceService;
use App\Support\AccessControl;
use Illuminate\Auth\Access\AuthorizationException;

class AiTravelController extends Controller
{
    public function __construct(private readonly AIFinanceService $aiFinanceService) {}

    public function plan(TravelPlanRequest $request)
    {
        $user = $request->user();
        if ($user === null || ! AccessControl::canAccessModule($user, 'travel_planner')) {
            throw new AuthorizationException(AccessControl::moduleAccessDeniedMessage('travel_planner'));
        }

        return response()->json(
            $this->aiFinanceService->travelPlan($user, $request->validated()),
        );
    }
}
