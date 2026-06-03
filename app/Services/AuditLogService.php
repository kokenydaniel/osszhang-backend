<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogService
{
    public function record(
        string $action,
        ?int $userId = null,
        ?int $householdId = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?array $payload = null,
        ?Request $request = null,
    ): AuditLog {
        return AuditLog::create([
            'action' => $action,
            'user_id' => $userId,
            'household_id' => $householdId,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'payload' => $payload,
            'ip' => $request?->ip(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listRecent(int $limit = 100): array
    {
        return AuditLog::query()
            ->with(['user:id,first_name,last_name,username', 'household:id,name'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => trim($log->user->first_name.' '.$log->user->last_name) ?: $log->user->username,
                ] : null,
                'household' => $log->household ? ['id' => $log->household->id, 'name' => $log->household->name] : null,
                'subject_type' => $log->subject_type,
                'subject_id' => $log->subject_id,
                'payload' => $log->payload,
                'ip' => $log->ip,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
