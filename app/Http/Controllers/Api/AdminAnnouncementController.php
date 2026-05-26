<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SystemAnnouncementResource;
use App\Models\SystemAnnouncement;
use App\Services\AdminAnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function __construct(
        private readonly AdminAnnouncementService $adminAnnouncementService,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => SystemAnnouncementResource::collection(
                $this->adminAnnouncementService->listAnnouncements(),
            )->resolve(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:3', 'max:2000'],
            'type' => ['required', 'string', 'in:info,warning,danger'],
        ]);

        $this->adminAnnouncementService->validateType($validated['type']);

        return response()->json([
            'data' => $this->adminAnnouncementService->createAnnouncement(
                $validated['message'],
                $validated['type'],
            ),
        ], 201);
    }

    public function toggle(SystemAnnouncement $announcement): JsonResponse
    {
        return response()->json([
            'data' => $this->adminAnnouncementService->toggleActive($announcement),
        ]);
    }
}
