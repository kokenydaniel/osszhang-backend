<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserFeedbackReport;
use App\Models\UserFeedbackReportAttachment;
use App\Services\UserFeedbackService;
use App\Support\FeedbackConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminFeedbackReportController extends Controller
{
    public function __construct(private readonly UserFeedbackService $feedback) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', 'nullable', 'string', Rule::in(FeedbackConfig::statuses())],
            'category' => ['sometimes', 'nullable', 'string', Rule::in(FeedbackConfig::allowedCategoryInputs())],
        ]);

        return response()->json([
            'data' => $this->feedback->listForAdmin(
                $validated['status'] ?? null,
                $validated['category'] ?? null,
            ),
            'meta' => [
                'attentionCount' => $this->feedback->adminAttentionCount(),
            ],
        ]);
    }

    public function attentionCount(): JsonResponse
    {
        return response()->json([
            'data' => ['count' => $this->feedback->adminAttentionCount()],
        ]);
    }

    public function show(UserFeedbackReport $feedbackReport): JsonResponse
    {
        return response()->json([
            'data' => $this->feedback->showForAdmin($feedbackReport),
        ]);
    }

    public function storeMessage(Request $request, UserFeedbackReport $feedbackReport): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        return response()->json([
            'data' => $this->feedback->addAdminMessage(
                $request->user(),
                $feedbackReport,
                $validated['body'],
            ),
        ]);
    }

    public function update(Request $request, UserFeedbackReport $feedbackReport): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(FeedbackConfig::statuses())],
        ]);

        return response()->json([
            'data' => $this->feedback->updateStatus($feedbackReport, $validated['status']),
        ]);
    }

    public function downloadAttachment(UserFeedbackReportAttachment $attachment)
    {
        return $this->feedback->downloadAttachment($attachment);
    }

    public function downloadLegacyAttachment(UserFeedbackReport $feedbackReport)
    {
        abort_unless($feedbackReport->path && $feedbackReport->disk, 404);

        abort_unless(\App\Support\StorageLocator::exists($feedbackReport->disk, $feedbackReport->path), 404);
        $disk = \App\Support\StorageLocator::forPath($feedbackReport->disk, $feedbackReport->path);

        return $disk->download($feedbackReport->path, $feedbackReport->original_name ?? 'csatolmany', [
            'Content-Type' => $feedbackReport->mime ?? 'application/octet-stream',
        ]);
    }
}
