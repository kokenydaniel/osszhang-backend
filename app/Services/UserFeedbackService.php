<?php

namespace App\Services;

use App\Support\FeedbackConfig;
use App\Support\HouseholdFileCipher;
use App\Support\HouseholdFileStorage;
use App\Models\User;
use App\Models\UserFeedbackReport;
use App\Models\UserFeedbackReportAttachment;
use App\Models\UserFeedbackReportMessage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class UserFeedbackService
{
    /** @param  array<int, UploadedFile>  $files */
    public function store(
        User $user,
        string $category,
        string $message,
        ?string $subject = null,
        ?string $pageUrl = null,
        array $files = [],
    ): array {
        $category = $this->normalizeCategory($category);

        $report = UserFeedbackReport::create([
            'user_id' => $user->id,
            'household_id' => $user->household_id,
            'category' => $category,
            'subject' => $subject ? trim($subject) : null,
            'message' => trim($message),
            'status' => 'new',
            'page_url' => $pageUrl ? mb_substr(trim($pageUrl), 0, 512) : null,
            'user_last_read_at' => now(),
        ]);

        foreach ($files as $file) {
            $this->storeAttachment($report, $file);
        }

        self::forgetAdminAttentionCountCache();

        return $this->format($report->fresh()->load(['user', 'household', 'attachments', 'messages.user']));
    }

    /** @return array<int, array<string, mixed>> */
    public function listForUser(User $user): array
    {
        return UserFeedbackReport::query()
            ->with(['attachments', 'messages'])
            ->where('user_id', $user->id)
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (UserFeedbackReport $r) => $this->format($r, forUser: true))
            ->all();
    }

    public function showForUser(User $user, UserFeedbackReport $report): array
    {
        $this->assertOwner($user, $report);

        $report->load(['attachments', 'messages.user']);
        $report->update(['user_last_read_at' => now()]);

        return $this->format($report->fresh()->load(['attachments', 'messages.user']), forUser: true);
    }

    public function addUserMessage(User $user, UserFeedbackReport $report, string $body): array
    {
        $this->assertOwner($user, $report);
        abort_if($report->status === 'resolved', 422, 'A lezárt bejelentéshez nem lehet üzenetet küldeni.');

        $trimmed = trim($body);
        abort_if(mb_strlen($trimmed) < 2, 422, 'Az üzenet túl rövid.');

        UserFeedbackReportMessage::create([
            'user_feedback_report_id' => $report->id,
            'user_id' => $user->id,
            'author' => 'user',
            'body' => $trimmed,
        ]);

        $report->update([
            'status' => 'new',
            'user_last_read_at' => now(),
        ]);

        self::forgetAdminAttentionCountCache();

        return $this->format($report->fresh()->load(['attachments', 'messages.user']), forUser: true);
    }

    /** @return array<int, array<string, mixed>> */
    public function listForAdmin(?string $status = null, ?string $category = null): array
    {
        $query = UserFeedbackReport::query()
            ->with(['user', 'household', 'attachments', 'messages'])
            ->orderByDesc('updated_at');

        if ($status && in_array($status, FeedbackConfig::statuses(), true)) {
            $query->where('status', $status);
        }

        if ($category) {
            $normalized = $this->normalizeCategory($category);
            $query->where('category', $normalized);
        }

        return $query->limit(500)->get()->map(fn (UserFeedbackReport $r) => $this->format($r))->all();
    }

    public function showForAdmin(UserFeedbackReport $report): array
    {
        $report->load(['user', 'household', 'attachments', 'messages.user']);
        $report->update([
            'admin_last_read_at' => now(),
            'status' => $report->status === 'new' ? 'read' : $report->status,
        ]);

        return $this->format($report->fresh()->load(['user', 'household', 'attachments', 'messages.user']));
    }

    public function addAdminMessage(User $admin, UserFeedbackReport $report, string $body): array
    {
        $trimmed = trim($body);
        abort_if(mb_strlen($trimmed) < 2, 422, 'A válasz túl rövid.');

        UserFeedbackReportMessage::create([
            'user_feedback_report_id' => $report->id,
            'user_id' => $admin->id,
            'author' => 'admin',
            'body' => $trimmed,
        ]);

        $report->update([
            'status' => 'replied',
            'admin_last_read_at' => now(),
            'user_last_read_at' => null,
        ]);

        self::forgetAdminAttentionCountCache();

        return $this->format($report->fresh()->load(['user', 'household', 'attachments', 'messages.user']));
    }

    public function adminAttentionCount(): int
    {
        return (int) Cache::remember('admin.feedback.attention_count', now()->addSeconds(30), function (): int {
            return UserFeedbackReport::query()
                ->where('status', '!=', 'resolved')
                ->where(function ($q) {
                    $q->where('status', 'new')
                        ->orWhere(function ($q2) {
                            $q2->whereHas('messages', function ($mq) {
                                $mq->where('author', 'user')
                                    ->where(function ($inner) {
                                        $inner->whereNull('user_feedback_reports.admin_last_read_at')
                                            ->orWhereColumn(
                                                'user_feedback_report_messages.created_at',
                                                '>',
                                                'user_feedback_reports.admin_last_read_at',
                                            );
                                    });
                            });
                        });
                })
                ->count();
        });
    }

    public static function forgetAdminAttentionCountCache(): void
    {
        Cache::forget('admin.feedback.attention_count');
    }

    public function userUnreadCount(User $user): int
    {
        return UserFeedbackReport::query()
            ->where('user_id', $user->id)
            ->where('status', 'replied')
            ->where(function ($q) {
                $q->whereNull('user_last_read_at')
                    ->orWhereHas('messages', function ($mq) {
                        $mq->where('author', 'admin')
                            ->whereColumn(
                                'user_feedback_report_messages.created_at',
                                '>',
                                'user_feedback_reports.user_last_read_at',
                            );
                    });
            })
            ->count();
    }

    public function updateStatus(UserFeedbackReport $report, string $status): array
    {
        abort_unless(in_array($status, FeedbackConfig::statuses(), true), 422, 'Érvénytelen státusz.');

        $report->update(['status' => $status]);

        self::forgetAdminAttentionCountCache();

        return $this->format($report->fresh()->load(['user', 'household', 'attachments', 'messages.user']));
    }

    public function downloadAttachmentForUser(User $user, UserFeedbackReportAttachment $attachment): Response
    {
        $report = $attachment->report;
        abort_unless($report !== null, 404);
        $this->assertOwner($user, $report);

        return $this->streamAttachment($report, $attachment);
    }

    private function streamAttachment(UserFeedbackReport $report, UserFeedbackReportAttachment $attachment): Response
    {
        return HouseholdFileStorage::downloadResponse(
            HouseholdFileCipher::userScope($report->user_id),
            $attachment->disk,
            $attachment->path,
            $attachment->original_name,
            $attachment->mime,
            $attachment->size_bytes > 0 ? $attachment->size_bytes : null,
        );
    }

    public function resolveAttachment(int $attachmentId): UserFeedbackReportAttachment
    {
        return UserFeedbackReportAttachment::query()->whereKey($attachmentId)->firstOrFail();
    }

    private function assertOwner(User $user, UserFeedbackReport $report): void
    {
        abort_unless($report->user_id === $user->id, 403);
    }

    private function storeAttachment(UserFeedbackReport $report, UploadedFile $file): void
    {
        $scope = HouseholdFileCipher::userScope($report->user_id);
        $dir = 'feedback-reports/'.$report->id;
        $stored = HouseholdFileStorage::store($scope, $dir, $file);

        UserFeedbackReportAttachment::create([
            'user_feedback_report_id' => $report->id,
            'disk' => $stored['disk'],
            'path' => $stored['path'],
            'original_name' => $stored['original_name'],
            'mime' => $stored['mime'],
            'size_bytes' => $stored['size_bytes'],
        ]);
    }

    private function normalizeCategory(string $category): string
    {
        $legacy = FeedbackConfig::legacyCategories();
        if (isset($legacy[$category])) {
            return $legacy[$category];
        }

        abort_unless(in_array($category, FeedbackConfig::categories(), true), 422, 'Érvénytelen típus.');

        return $category;
    }

    /** @return array<string, mixed> */
    private function format(UserFeedbackReport $report, bool $forUser = false): array
    {
        $user = $report->relationLoaded('user') ? $report->user : null;
        $household = $report->relationLoaded('household') ? $report->household : null;
        $attachments = $this->resolveAttachments($report);
        $messages = $this->resolveMessages($report);
        $lastAdminMessageAt = $this->lastMessageAt($report, 'admin');
        $lastUserFollowUpAt = $this->lastUserFollowUpAt($report);

        $hasUnreadReply = $forUser && $lastAdminMessageAt !== null && (
            $report->user_last_read_at === null
            || $lastAdminMessageAt->gt($report->user_last_read_at)
        );

        $needsAdminAttention = ! $forUser && $report->status !== 'resolved' && (
            $report->status === 'new'
            || ($lastUserFollowUpAt !== null && (
                $report->admin_last_read_at === null
                || $lastUserFollowUpAt->gt($report->admin_last_read_at)
            ))
        );

        return [
            'id' => $report->id,
            'category' => $report->category,
            'subject' => $report->subject,
            'message' => $report->message,
            'status' => $report->status,
            'pageUrl' => $report->page_url,
            'attachments' => $attachments,
            'hasAttachment' => count($attachments) > 0,
            'messages' => $messages,
            'hasUnreadReply' => $hasUnreadReply,
            'needsAdminAttention' => $needsAdminAttention,
            'createdAt' => $report->created_at?->toIso8601String(),
            'updatedAt' => $report->updated_at?->toIso8601String(),
            'user' => $user ? [
                'id' => $user->id,
                'username' => $user->username,
                'firstName' => $user->first_name,
                'lastName' => $user->last_name,
            ] : null,
            'household' => $household ? [
                'id' => $household->id,
                'name' => $household->name,
            ] : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveMessages(UserFeedbackReport $report): array
    {
        if (! $report->relationLoaded('messages')) {
            return [];
        }

        return $report->messages->map(function (UserFeedbackReportMessage $m) {
            $author = $m->relationLoaded('user') ? $m->user : null;

            return [
                'id' => $m->id,
                'author' => $m->author,
                'body' => $m->body,
                'createdAt' => $m->created_at?->toIso8601String(),
                'user' => $author ? [
                    'id' => $author->id,
                    'username' => $author->username,
                    'firstName' => $author->first_name,
                    'lastName' => $author->last_name,
                ] : null,
            ];
        })->all();
    }

    private function lastMessageAt(UserFeedbackReport $report, string $author): ?\Illuminate\Support\Carbon
    {
        if (! $report->relationLoaded('messages')) {
            return null;
        }

        $latest = $report->messages->where('author', $author)->sortByDesc('created_at')->first();

        return $latest?->created_at;
    }

    private function lastUserFollowUpAt(UserFeedbackReport $report): ?\Illuminate\Support\Carbon
    {
        if (! $report->relationLoaded('messages')) {
            return null;
        }

        $latest = $report->messages->where('author', 'user')->sortByDesc('created_at')->first();

        return $latest?->created_at;
    }

    /** @return array<int, array<string, mixed>> */
    private function resolveAttachments(UserFeedbackReport $report): array
    {
        if ($report->relationLoaded('attachments') && $report->attachments->isNotEmpty()) {
            return $report->attachments->map(fn (UserFeedbackReportAttachment $a) => $this->formatAttachment($a))->all();
        }

        if ($report->path && $report->disk) {
            return [[
                'id' => 0,
                'originalName' => $report->original_name,
                'mime' => $report->mime,
                'sizeBytes' => (int) $report->size_bytes,
                'legacy' => true,
            ]];
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function formatAttachment(UserFeedbackReportAttachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'originalName' => $attachment->original_name,
            'mime' => $attachment->mime,
            'sizeBytes' => (int) $attachment->size_bytes,
            'legacy' => false,
        ];
    }
}
