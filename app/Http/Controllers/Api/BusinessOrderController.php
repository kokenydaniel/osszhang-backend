<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessOrder\StoreBusinessOrderRequest;
use App\Http\Requests\BusinessOrder\UpdateBusinessOrderRequest;
use App\Models\BusinessOrder;
use App\Services\BusinessOrderService;
use App\Services\ShopifyImportService;
use Illuminate\Http\Request;

class BusinessOrderController extends Controller
{
    public function __construct(
        private readonly BusinessOrderService $businessOrderService,
        private readonly ShopifyImportService $shopifyImportService,
    ) {}

    public function index(Request $request)
    {
        return response()->json(
            $this->businessOrderService->listForHousehold($request->user()->household),
        );
    }

    public function store(StoreBusinessOrderRequest $request)
    {
        return response()->json(
            $this->businessOrderService->create($request->user()->household, $request->validated()),
            201,
        );
    }

    public function update(UpdateBusinessOrderRequest $request, BusinessOrder $businessOrder)
    {
        return response()->json(
            $this->businessOrderService->update(
                $request->user()->household,
                $businessOrder,
                $request->validated(),
                $request->only(['channel', 'paymentMethod', 'provider', 'destination', 'invoiceId', 'paidDate', 'state']),
            ),
        );
    }

    public function destroy(BusinessOrder $businessOrder)
    {
        $this->businessOrderService->delete($businessOrder);

        return response()->json(null, 204);
    }

    public function shopifyImport(Request $request)
    {
        try {
            $result = $this->shopifyImportService->import($request->user());
            $status = $result['status'];
            unset($result['status']);

            return response()->json($result, $status);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
