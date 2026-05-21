<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AIFinance\AutoCategorizeTransactionRequest;
use App\Http\Requests\AIFinance\MonthYearRequest;
use App\Http\Requests\AIFinance\SavingsRecommendationsRequest;
use App\Services\AIFinanceService;
use Illuminate\Http\Request;

class AIFinanceController extends Controller
{
    public function __construct(private readonly AIFinanceService $aiFinanceService) {}

    public function autoCategorizeTransaction(AutoCategorizeTransactionRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->autoCategorizeTransaction($request->validated()),
        );
    }

    public function overspendRootCause(MonthYearRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->overspendRootCause($request->user()->household, $request->validated()),
        );
    }

    public function cashflowForecast(MonthYearRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->cashflowForecast($request->user()->household, $request->validated()),
        );
    }

    public function utilityAnomalies(MonthYearRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->utilityAnomalies($request->user()->household, $request->validated()),
        );
    }

    public function savingsRecommendations(SavingsRecommendationsRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->savingsRecommendations($request->user()->household, $request->validated()),
        );
    }

    public function optimizeDebts(Request $request)
    {
        return response()->json(
            $this->aiFinanceService->optimizeDebts(
                $request->user()->household,
                $request->input('strategy', 'avalanche'),
            ),
        );
    }

    public function weeklyBriefing(Request $request)
    {
        return response()->json(
            $this->aiFinanceService->weeklyBriefing(
                $request->user()->household,
                $request->query('week_start'),
            ),
        );
    }
}
