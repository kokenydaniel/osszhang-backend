<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Investment\StoreInvestmentRequest;
use App\Http\Requests\Investment\UpdateInvestmentRequest;
use App\Services\InvestmentService;
use Illuminate\Http\Request;

class InvestmentController extends Controller
{
    public function __construct(private readonly InvestmentService $investmentService) {}

    public function index(Request $request)
    {
        return response()->json($this->investmentService->listForHousehold($request->user()->household));
    }

    public function store(StoreInvestmentRequest $request)
    {
        return response()->json(
            $this->investmentService->create($request->user()->household, $request->validated()),
            201,
        );
    }

    public function update(UpdateInvestmentRequest $request, $id)
    {
        return response()->json(
            $this->investmentService->update($request->user()->household, $id, $request->validated()),
        );
    }

    public function destroy(Request $request, $id)
    {
        $this->investmentService->delete($request->user()->household_id, $id);

        return response()->json(null, 204);
    }
}
