<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\CollectsUploadedFiles;
use App\Http\Controllers\Api\Concerns\ValidatesFeedbackAttachments;
use App\Http\Controllers\Controller;
use App\Models\UserFeedbackReport;
use App\Services\UserFeedbackService;
use App\Support\FeedbackConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FeedbackReportController extends Controller
{
    use CollectsUploadedFiles;
    use ValidatesFeedbackAttachments;

    public function __construct(private readonly UserFeedbackService $feedback) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->feedback->listForUser($request->user()),
            'meta' => [
                'unreadCount' => $this->feedback->userUnreadCount($request->user()),
            ],
        ]);
    }

    public function show(Request $request, UserFeedbackReport $feedbackReport): JsonResponse
    {
        return response()->json([
            'data' => $this->feedback->showForUser($request->user(), $feedbackReport),
        ]);
    }

    public function storeMessage(Request $request, UserFeedbackReport $feedbackReport): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
        ]);

        return response()->json([
            'data' => $this->feedback->addUserMessage(
                $request->user(),
                $feedbackReport,
                $validated['body'],
            ),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $maxMessage = (int) (config('feedback.max_message_length') ?? 5000);
        $maxSubject = (int) (config('feedback.max_subject_length') ?? 200);
        $maxKb = (int) (config('feedback.attachment_max_kb') ?? 10240);
        $mimes = config('feedback.attachment_mimes') ?? ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif'];
        $maxFiles = (int) (config('feedback.max_files') ?? 5);

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(FeedbackConfig::allowedCategoryInputs())],
            'message' => ['required', 'string', 'min:10', 'max:'.$maxMessage],
            'subject' => ['nullable', 'string', 'max:'.$maxSubject],
            'page_url' => ['nullable', 'string', 'max:512'],
        ]);

        $files = $this->collectUploadedFiles($request);

        if (count($files) > $maxFiles) {
            throw ValidationException::withMessages([
                'files' => ["Legfeljebb {$maxFiles} fájl csatolható."],
            ]);
        }

        foreach ($files as $index => $file) {
            $this->validateFeedbackFile($file, $maxKb, $mimes, $index);
        }

        $data = $this->feedback->store(
            $request->user(),
            $validated['category'],
            $validated['message'],
            $validated['subject'] ?? null,
            $validated['page_url'] ?? null,
            $files,
        );

        return response()->json(['data' => $data], 201);
    }
}
