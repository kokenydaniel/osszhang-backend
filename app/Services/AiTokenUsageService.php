<?php

namespace App\Services;

use App\Models\AiTokenUsageEvent;
use App\Support\AiTokenPricing;
use App\Support\AiUsageContext;
use Illuminate\Support\Facades\Log;

class AiTokenUsageService
{

    public function record(?AiUsageContext $context, string $model, array $usage): void
    {
        if ($context === null || $context->householdId === null) {
            return;
        }

        $promptTokens = max(0, (int) ($usage['prompt_tokens'] ?? 0));
        $completionTokens = max(0, (int) ($usage['completion_tokens'] ?? 0));
        $cachedTokens = max(0, (int) ($usage['cached_tokens'] ?? 0));
        $reasoningTokens = max(0, (int) ($usage['reasoning_tokens'] ?? 0));
        $totalTokens = max(0, (int) ($usage['total_tokens'] ?? ($promptTokens + $completionTokens)));

        if ($totalTokens === 0) {
            return;
        }

        $resolvedModel = $model !== '' ? $model : (string) config('openai.model', '');
        $cost = AiTokenPricing::calculateCostUsd($resolvedModel, [
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cached_tokens' => $cachedTokens,
            'reasoning_tokens' => $reasoningTokens,
        ]);

        if ($cost === null) {
            Log::warning('AI token cost not recorded: no OpenAI pricing entry for model.', [
                'model' => $resolvedModel,
                'feature' => $context->feature,
                'household_id' => $context->householdId,
            ]);
        }

        AiTokenUsageEvent::create([
            'household_id' => $context->householdId,
            'user_id' => $context->userId,
            'feature' => $context->feature,
            'model' => $resolvedModel !== '' ? $resolvedModel : null,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'cached_tokens' => $cachedTokens,
            'reasoning_tokens' => $reasoningTokens,
            'total_tokens' => $totalTokens,
            'cost_usd' => $cost['cost_usd'] ?? null,
        ]);
    }

    public function monthlyTotalTokens(int $householdId): int
    {
        return (int) AiTokenUsageEvent::query()
            ->where('household_id', $householdId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_tokens');
    }

    public function householdSummary(int $householdId): array
    {
        $query = AiTokenUsageEvent::query()->where('household_id', $householdId);
        $monthStart = now()->startOfMonth();

        $totals = (clone $query)
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost_usd')
            ->selectRaw('SUM(CASE WHEN cost_usd IS NULL THEN 1 ELSE 0 END) as requests_without_cost')
            ->selectRaw('COUNT(*) as request_count')
            ->first();

        $monthTotals = (clone $query)
            ->where('created_at', '>=', $monthStart)
            ->selectRaw('COALESCE(SUM(prompt_tokens), 0) as prompt_tokens')
            ->selectRaw('COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost_usd')
            ->selectRaw('SUM(CASE WHEN cost_usd IS NULL THEN 1 ELSE 0 END) as requests_without_cost')
            ->selectRaw('COUNT(*) as request_count')
            ->first();

        $byFeature = (clone $query)
            ->selectRaw('feature')
            ->selectRaw('COALESCE(SUM(total_tokens), 0) as total_tokens')
            ->selectRaw('COALESCE(SUM(cost_usd), 0) as cost_usd')
            ->selectRaw('SUM(CASE WHEN cost_usd IS NULL THEN 1 ELSE 0 END) as requests_without_cost')
            ->selectRaw('COUNT(*) as request_count')
            ->groupBy('feature')
            ->orderByDesc('total_tokens')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'feature' => (string) $row->feature,
                'total_tokens' => (int) $row->total_tokens,
                'request_count' => (int) $row->request_count,
                'cost_usd' => round((float) ($row->cost_usd ?? 0), 8),
                'requests_without_cost' => (int) ($row->requests_without_cost ?? 0),
            ])
            ->values()
            ->all();

        $lastUsedAt = (clone $query)->max('created_at');

        return [
            'total_prompt_tokens' => (int) ($totals->prompt_tokens ?? 0),
            'total_completion_tokens' => (int) ($totals->completion_tokens ?? 0),
            'total_tokens' => (int) ($totals->total_tokens ?? 0),
            'request_count' => (int) ($totals->request_count ?? 0),
            'cost_usd' => round((float) ($totals->cost_usd ?? 0), 8),
            'requests_without_cost' => (int) ($totals->requests_without_cost ?? 0),
            'month_prompt_tokens' => (int) ($monthTotals->prompt_tokens ?? 0),
            'month_completion_tokens' => (int) ($monthTotals->completion_tokens ?? 0),
            'month_total_tokens' => (int) ($monthTotals->total_tokens ?? 0),
            'month_request_count' => (int) ($monthTotals->request_count ?? 0),
            'month_cost_usd' => round((float) ($monthTotals->cost_usd ?? 0), 8),
            'month_requests_without_cost' => (int) ($monthTotals->requests_without_cost ?? 0),
            'by_feature' => $byFeature,
            'last_used_at' => $lastUsedAt ? (string) $lastUsedAt : null,
            'pricing_configured' => AiTokenPricing::isConfigured(),
            'pricing' => AiTokenPricing::pricingMeta(),
        ];
    }
}
