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
            $this->aiFinanceService->overspendRootCause(
                $request->user()->household,
                $request->user(),
                $request->validated(),
            ),
        );
    }

    public function cashflowForecast(MonthYearRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->cashflowForecast(
                $request->user()->household,
                $request->user(),
                $request->validated(),
            ),
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
            $this->aiFinanceService->savingsRecommendations(
                $request->user()->household,
                $request->user(),
                $request->validated(),
            ),
        );
    }

    public function optimizeDebts(Request $request)
    {
        $walletId = $request->input('wallet_id');
        $walletId = $walletId !== null && $walletId !== '' ? (int) $walletId : null;

        return response()->json(
            $this->aiFinanceService->optimizeDebts(
                $request->user()->household,
                $request->user(),
                $request->input('strategy', 'avalanche'),
                $walletId,
            ),
        );
    }

    public function weeklyBriefing(Request $request)
    {
        $walletId = $request->query('wallet_id');
        $walletId = $walletId !== null && $walletId !== '' ? (int) $walletId : null;

        return response()->json(
            $this->aiFinanceService->weeklyBriefing(
                $request->user()->household,
                $request->user(),
                $request->query('week_start'),
                $walletId,
            ),
        );
    }

    public function paymentPriority(MonthYearRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->paymentPriority(
                $request->user()->household,
                $request->user(),
                $request->validated(),
            ),
        );
    }

    public function vatEstimate(MonthYearRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->vatEstimate(
                $request->user()->household,
                $request->validated(),
            ),
        );
    }

    public function costReductionSuggestions(MonthYearRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->costReductionSuggestions(
                $request->user()->household,
                $request->user(),
                $request->validated(),
            ),
        );
    }
}
