<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeatureFlagResource;
use App\Services\AdminFeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminFeatureController extends Controller
{
    public function __construct(
        private readonly AdminFeatureService $adminFeatureService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => FeatureFlagResource::collection($this->adminFeatureService->listFlags())->resolve(),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $validated = $request->validate([
            'value' => ['required', 'boolean'],
        ]);

        return response()->json([
            'data' => $this->adminFeatureService->updateFlag($key, (bool) $validated['value'], $request),
        ]);
    }
}
