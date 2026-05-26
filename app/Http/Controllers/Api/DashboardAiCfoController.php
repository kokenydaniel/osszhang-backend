<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AIFinance\AiCfoRequest;
use App\Services\AIFinanceService;

class DashboardAiCfoController extends Controller
{
    public function __construct(private readonly AIFinanceService $aiFinanceService) {}

    public function __invoke(AiCfoRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->aiCfo(
                $request->user()->household,
                $request->user(),
                $request->validated(),
            ),
        );
    }
}
