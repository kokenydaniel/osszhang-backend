<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AI\QueryRequest;
use App\Services\AIFinanceService;

class AIController extends Controller
{
    public function __construct(private readonly AIFinanceService $aiFinanceService) {}

    public function query(QueryRequest $request)
    {
        return response()->json(
            $this->aiFinanceService->query(
                $request->user(),
                $request->input('prompt'),
                (bool) $request->input('include_context', false),
            ),
        );
    }
}
