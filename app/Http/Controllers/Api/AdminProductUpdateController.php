<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductUpdateResource;
use App\Models\ProductUpdate;
use App\Services\AdminProductUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminProductUpdateController extends Controller
{
    public function __construct(
        private readonly AdminProductUpdateService $adminProductUpdateService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => ProductUpdateResource::collection(
                $this->adminProductUpdateService->listUpdates(),
            )->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->adminProductUpdateService->createUpdate($request->all()),
        ], 201);
    }

    public function update(Request $request, ProductUpdate $productUpdate): JsonResponse
    {
        return response()->json([
            'data' => $this->adminProductUpdateService->updateUpdate($productUpdate, $request->all()),
        ]);
    }

    public function destroy(ProductUpdate $productUpdate): JsonResponse
    {
        $this->adminProductUpdateService->deleteUpdate($productUpdate);

        return response()->json(['data' => null]);
    }

    public function toggle(ProductUpdate $productUpdate): JsonResponse
    {
        return response()->json([
            'data' => $this->adminProductUpdateService->toggleActive($productUpdate),
        ]);
    }
}
