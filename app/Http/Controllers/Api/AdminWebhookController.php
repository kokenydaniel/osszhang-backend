<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminWebhookController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogService) {}

    public function index()
    {
        return response()->json([
            'data' => Webhook::query()->orderByDesc('id')->get()->map(fn (Webhook $w) => [
                'id' => $w->id,
                'household_id' => $w->household_id,
                'url' => $w->url,
                'events' => $w->events,
                'is_active' => $w->is_active,
                'created_at' => $w->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2048',
            'events' => 'required|array|min:1',
            'events.*' => 'string|max:100',
            'household_id' => 'nullable|integer|exists:households,id',
        ]);

        $webhook = Webhook::create([
            'household_id' => $validated['household_id'] ?? null,
            'created_by' => $request->user()->id,
            'url' => $validated['url'],
            'secret' => Str::random(48),
            'events' => $validated['events'],
            'is_active' => true,
        ]);

        $this->auditLogService->record(
            'webhook.created',
            $request->user()->id,
            $webhook->household_id,
            Webhook::class,
            $webhook->id,
            ['url' => $webhook->url, 'events' => $webhook->events],
            $request,
        );

        return response()->json(['data' => ['id' => $webhook->id, 'secret' => $webhook->secret]], 201);
    }

    public function destroy(Webhook $webhook, Request $request)
    {
        $this->auditLogService->record(
            'webhook.deleted',
            $request->user()->id,
            $webhook->household_id,
            Webhook::class,
            $webhook->id,
            ['url' => $webhook->url],
            $request,
        );
        $webhook->delete();

        return response()->json(null, 204);
    }
}
