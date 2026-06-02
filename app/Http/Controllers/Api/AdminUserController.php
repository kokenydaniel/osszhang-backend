<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateAdminUserTierGrantRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function __construct(
        private readonly AdminUserService $adminUserService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adminUserService->listUsers($request);

        return response()->json([
            'data' => AdminUserResource::collection($paginator->items())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function activate(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->adminUserService->activate($user),
        ]);
    }

    public function deactivate(Request $request, User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->adminUserService->deactivate($request->user(), $user),
        ]);
    }

    public function impersonate(Request $request, User $user): JsonResponse
    {
        return response()->json(
            $this->adminUserService->impersonate($request->user(), $user, $request),
        );
    }

    public function updateTierGrant(UpdateAdminUserTierGrantRequest $request, User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->adminUserService->updateTierGrant($request->user(), $user, $request),
        ]);
    }
}
