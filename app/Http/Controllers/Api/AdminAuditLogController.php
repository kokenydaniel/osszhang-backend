<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;

class AdminAuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index()
    {
        return response()->json(['data' => $this->auditLogService->listRecent(200)]);
    }
}
