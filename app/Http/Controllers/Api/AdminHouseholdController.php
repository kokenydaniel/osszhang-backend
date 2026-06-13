<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DestroyAdminHouseholdRequest;
use App\Http\Requests\Admin\UpdateAdminHouseholdAiSettingsRequest;
use App\Http\Requests\Admin\UpdateAdminUserTierGrantRequest;
use App\Http\Resources\AdminHouseholdResource;
use App\Models\Household;
use App\Services\AdminHouseholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminHouseholdController extends Controller
{
    public function __construct(
        private readonly AdminHouseholdService $adminHouseholdService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adminHouseholdService->listHouseholds($request);

        return response()->json([
            'data' => collect($paginator->items())
                ->map(fn (Household $household) => (new AdminHouseholdResource($household))->resolve())
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Household $household): JsonResponse
    {
        return response()->json([
            'data' => $this->adminHouseholdService->show($household),
        ]);
    }

    public function updateTierGrant(UpdateAdminUserTierGrantRequest $request, Household $household): JsonResponse
    {
        return response()->json([
            'data' => $this->adminHouseholdService->updateTierGrant($request->user(), $household, $request),
        ]);
    }

    public function updateAiSettings(UpdateAdminHouseholdAiSettingsRequest $request, Household $household): JsonResponse
    {
        return response()->json([
            'data' => $this->adminHouseholdService->updateAiSettings($request->user(), $household, $request),
        ]);
    }

    public function destroy(DestroyAdminHouseholdRequest $request, Household $household): JsonResponse
    {
        $this->adminHouseholdService->destroy($request->user(), $household, $request);

        return response()->json([
            'message' => 'A háztartás és minden tagja törölve.',
        ]);
    }
}
